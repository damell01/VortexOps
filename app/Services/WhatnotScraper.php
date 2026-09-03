<?php

namespace App\Services;

use App\Models\Shipment;
use App\Models\Show;
use App\Models\ShowIngestionLog;
use App\Models\StreamerLogEntry;
use App\Models\WhatnotChannel;
use App\Models\WhatnotLedgerEntry;
use App\Models\WhatnotShowOrder;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class WhatnotScraper
{
    public const EXIT_SELECTOR_MISS  = 2;
    public const EXIT_AUTH_REQUIRED  = 3;
    public const EXIT_RATE_LIMITED   = 4;

    private string $scriptPath;
    private string $nodeBin;

    public function __construct()
    {
        $this->scriptPath = base_path('scripts/whatnot-runner.cjs');
        $this->nodeBin    = config('vortex.whatnot.node_bin', 'node');
    }

    public function testConnection(bool $debug = false): array
    {
        $env = $this->baseEnv($debug);
        $env['WHATNOT_MODE'] = 'test';
        $process = $this->makeProcess($env, timeout: 60);
        $this->withBrowserLock(fn () => $process->run());
        $stderr = trim($process->getErrorOutput());
        $stdout = trim($process->getOutput());
        if ($stderr) Log::channel('stack')->warning('WhatnotScraper testConnection stderr', ['output' => $stderr]);
        if (! $process->isSuccessful()) throw new \RuntimeException('Connection test failed: ' . ($stderr ?: "Scraper exited with code {$process->getExitCode()}"));
        $data = json_decode($stdout, true);
        if (json_last_error() !== JSON_ERROR_NONE || empty($data['connected'])) throw new \RuntimeException('Unexpected scraper response during test: ' . $stdout);
        return $data;
    }

    public function fetchShows(int $limit = 50, bool $debug = false, ?string $channelUsername = null, ?callable $onProgress = null, ?string $seedLiveId = null): array
    {
        $env = $this->baseEnv($debug);
        $env['WHATNOT_MODE'] = 'analytics';
        $env['WHATNOT_LIMIT'] = (string) $limit;
        if ($channelUsername) $env['WHATNOT_CHANNEL_NAME'] = $channelUsername;
        if ($seedLiveId) $env['WHATNOT_START_UUID'] = $seedLiveId;
        $timeoutSeconds = max(1200, (int) ceil($limit / 50) * 1200);
        $process = $this->makeProcess($env, timeout: $timeoutSeconds);
        $this->withBrowserLock(function () use ($process, $onProgress) {
            $onProgress ? $this->streamProcess($process, $onProgress) : $process->run();
        }, waitSeconds: $timeoutSeconds);
        $stderr = trim($process->getErrorOutput());
        $stdout = trim($process->getOutput());
        if ($stderr) {
            Log::channel('stack')->warning('WhatnotScraper stderr', ['output' => $stderr, 'channel' => $channelUsername]);
            if ($debug) fwrite(STDERR, $stderr . "\n");
        }
        $this->throwForExitCode((int) $process->getExitCode(), $stderr, $process->getCommandLine());
        if (! $process->isSuccessful()) throw new \RuntimeException('Whatnot scraper failed: ' . ($stderr ?: "Scraper exited with code {$process->getExitCode()}"));
        if (empty($stdout)) return [];
        $data = json_decode($stdout, true);
        if (json_last_error() !== JSON_ERROR_NONE) throw new \RuntimeException('Whatnot scraper returned invalid JSON: ' . json_last_error_msg());
        return is_array($data) ? $data : [];
    }

    public function fetchSellerShowUrls(bool $debug = false): array
    {
        $env = $this->baseEnv($debug); $env['WHATNOT_MODE'] = 'seller-shows';
        $process = $this->makeProcess($env, timeout: 120);
        $this->withBrowserLock(fn () => $process->run());
        $stderr = trim($process->getErrorOutput()); $stdout = trim($process->getOutput());
        if ($stderr) Log::channel('stack')->warning('WhatnotScraper seller-shows stderr', ['output' => $stderr]);
        $this->throwForExitCode((int)$process->getExitCode(), $stderr, $process->getCommandLine());
        if (!$process->isSuccessful()) throw new \RuntimeException('Seller-shows scraper failed: ' . ($stderr ?: "exit code {$process->getExitCode()}"));
        $data=json_decode($stdout,true); return is_array($data)?$data:[];
    }

    public function importDetailUrls(bool $debug = false): array
    {
        $rows=$this->fetchSellerShowUrls($debug); $updated=0; $unmatched=0;
        foreach($rows as $row){
            if(empty($row['detail_url'])) continue;
            $title=isset($row['title'])?trim($row['title']):null; $date=$row['show_date']??null;
            $query=Show::query()->whereNull('detail_url');
            if($title&&$date){$query->where(function($q)use($title,$date){$q->where(fn($q2)=>$q2->where('title',$title)->whereDate('show_date',$date))->orWhere(fn($q2)=>$q2->whereDate('show_date',$date));});}
            elseif($date)$query->whereDate('show_date',$date); elseif($title)$query->where('title',$title); else{$unmatched++;continue;}
            $show=$query->orderByRaw($title?'title = ? DESC':'id DESC',$title?[$title]:[])->first();
            if($show){$show->update(['detail_url'=>$row['detail_url']]);$updated++;Log::info('WhatnotScraper: backfilled detail_url',['show_id'=>$show->id,'url'=>$row['detail_url']]);}else$unmatched++;
        }
        return compact('updated','unmatched');
    }

    public function fetchShowOrders(string $showUrl, bool $debug = false): array
    {
        $env=$this->baseEnv($debug);$env['WHATNOT_MODE']='show-orders';$env['WHATNOT_SHOW_URL']=$showUrl;
        $timeout=1200;
        $process=$this->makeProcess($env, timeout:$timeout);
        $this->withBrowserLock(fn()=>$process->run(), waitSeconds:$timeout);
        $stderr=trim($process->getErrorOutput());$stdout=trim($process->getOutput());
        if($stderr)Log::channel('stack')->warning('WhatnotScraper show-orders stderr',['output'=>$stderr]);
        $this->throwForExitCode((int)$process->getExitCode(),$stderr,$process->getCommandLine());
        if(!$process->isSuccessful())throw new \RuntimeException('Order scraper failed: '.($stderr?:"Scraper exited with code {$process->getExitCode()}"));
        if(empty($stdout))return[];$data=json_decode($stdout,true);
        if(json_last_error()!==JSON_ERROR_NONE)throw new \RuntimeException('Order scraper returned invalid JSON: '.json_last_error_msg());
        return is_array($data)?$data:[];
    }

    public function importShowOrders(Show $show,bool $debug=false,?callable $onProgress=null):array
    {
        $liveId=$this->extractLiveIdFromUrl($show->detail_url);if(!$liveId)throw new \RuntimeException("Show #{$show->id} has no livestream id — run `php artisan whatnot:import` first to capture the Whatnot show URL.");
        $show->loadMissing('channel');$ordersByShow=$this->fetchOrdersForShows([['live_id'=>$liveId,'show_key'=>$show->id]],$show->channel?->whatnot_username,$debug,$onProgress);
        return $this->persistShowOrders($show,$ordersByShow[$show->id]??[]);
    }

    public function persistShowOrders(Show $show,array $rows):array
    {
        $created=0;$skipped=0;$updated=0;$defaultLocationId=$show->defaultInventoryLocation()?->id;
        $existingByOrderId=WhatnotShowOrder::where('show_id',$show->id)->whereNotNull('whatnot_order_id')->get()->keyBy('whatnot_order_id');
        $existingFallbackKeys=WhatnotShowOrder::where('show_id',$show->id)->whereNull('whatnot_order_id')->get(['buyer_username','item_name','lot_number'])->map(fn($r)=>"{$r->buyer_username}|{$r->item_name}|{$r->lot_number}")->flip()->toArray();
        foreach($rows as $row){$orderId=$row['order_id']??null;if($orderId&&$existingByOrderId->has($orderId)){if($this->mergeShipmentFields($existingByOrderId->get($orderId),$row))$updated++;else$skipped++;continue;}if(empty($row['buyer'])&&empty($row['item_name'])){$skipped++;continue;}if(!$orderId){$key=($row['buyer']??'').'|'.($row['item_name']??'').'|'.($row['lot_number']??'');if(isset($existingFallbackKeys[$key])){$skipped++;continue;}$existingFallbackKeys[$key]=true;}
            WhatnotShowOrder::create(['show_id'=>$show->id,'whatnot_order_id'=>$orderId,'whatnot_show_url'=>$show->detail_url,'inventory_location_id'=>$defaultLocationId,'buyer_username'=>$row['buyer']??null,'lot_number'=>$row['lot_number']??null,'item_name'=>$row['item_name']??null,'quantity'=>$row['quantity']??1,'unit_price'=>$row['unit_price']??null,'total_price'=>$row['total_price']??null,'status'=>$row['status']??'completed','show_date'=>$show->show_date,'raw_data'=>$row]);$created++;}
        Log::info('WhatnotScraper persistShowOrders complete',['show_id'=>$show->id,'created'=>$created,'updated'=>$updated,'skipped'=>$skipped]);return compact('created','skipped','updated');
    }

    public function persistShipments(Show $show,array $rows):array
    {
        $created=0;$updated=0;$skipped=0;$existingByTracking=Shipment::where('show_id',$show->id)->whereNotNull('tracking_number')->get()->keyBy('tracking_number');
        foreach($rows as $row){$trackingNumber=$row['tracking_number']??null;if(!$trackingNumber){$skipped++;continue;}if($existingByTracking->has($trackingNumber)){$shipment=$existingByTracking->get($trackingNumber);$updateData=array_filter(['buyer_username'=>$row['buyer']??null,'item_count'=>$row['quantity']??null,'shipping_cost'=>$row['total_price']??null,'weight_oz'=>$row['weight_oz']??null,'dimensions_json'=>$this->parseDimensions($row),'status'=>$row['shipping_status_scraped']??null,'carrier'=>$row['shipping_carrier']??null,'insurance_added'=>$this->detectInsuranceAdded($row),'signature_required'=>$this->detectSignatureRequired($row),'raw_payload'=>$row],fn($v)=>$v!==null&&$v!=='');if(!empty($updateData)){$shipment->update($updateData);$updated++;}continue;}Shipment::create(['show_id'=>$show->id,'whatnot_order_id'=>$row['order_id']??null,'buyer_username'=>$row['buyer']??null,'created_at_whatnot'=>$show->show_date,'item_count'=>$row['quantity']??null,'shipping_cost'=>$row['total_price']??null,'weight_oz'=>$row['weight_oz']??null,'dimensions_json'=>$this->parseDimensions($row),'status'=>$row['shipping_status_scraped']??null,'carrier'=>$row['shipping_carrier']??null,'tracking_number'=>$trackingNumber,'insurance_added'=>$this->detectInsuranceAdded($row),'signature_required'=>$this->detectSignatureRequired($row),'raw_payload'=>$row]);$created++;}
        Log::info('WhatnotScraper persistShipments complete',['show_id'=>$show->id,'created'=>$created,'updated'=>$updated,'skipped'=>$skipped]);return compact('created','updated','skipped');
    }

    private function parseDimensions(array $row):?array{$dims=array_filter(['length_in'=>$row['box_length_in']??null,'width_in'=>$row['box_width_in']??null,'height_in'=>$row['box_height_in']??null],fn($v)=>$v!==null);return !empty($dims)?$dims:null;}
    private function detectInsuranceAdded(array $row):bool{$text=($row['raw_text']??'').' '.implode(' ',$row);return(bool)preg_match('/insurance\s+added/i',$text);}
    private function detectSignatureRequired(array $row):bool{$text=($row['raw_text']??'').' '.implode(' ',$row);return(bool)preg_match('/signature\s+required/i',$text);}

    private function mergeShipmentFields(WhatnotShowOrder $order,array $row):bool
    {
        $fields=array_filter(['shipment_weight_oz'=>$row['weight_oz']??null,'box_length_in'=>$row['box_length_in']??null,'box_width_in'=>$row['box_width_in']??null,'box_height_in'=>$row['box_height_in']??null,'shipping_carrier'=>$row['shipping_carrier']??null,'shipping_service'=>$row['shipping_service']??null,'shipping_status'=>$row['shipping_status_scraped']??null],fn($v)=>$v!==null&&$v!=='');if(empty($fields))return false;
        if(isset($fields['shipping_status'])){$rank=array_flip(array_keys(WhatnotShowOrder::shippingStatusLabels()));$incomingRank=$rank[$fields['shipping_status']]??0;$currentRank=$order->shipping_status?($rank[$order->shipping_status]??0):-1;if($incomingRank<$currentRank)unset($fields['shipping_status']);}
        if(empty($fields))return false;$fields['shipment_synced_at']=now();$order->update($fields);return true;
    }

    public function refreshShipmentsForShows(\Illuminate\Support\Collection $shows,?string $channelUsername=null,bool $debug=false):array
    {
        $sources=[];$byKey=[];foreach($shows as $show){$liveId=$this->extractLiveIdFromUrl($show->detail_url);if(!$liveId)continue;$sources[]=['live_id'=>$liveId,'show_key'=>$show->id];$byKey[$show->id]=$show;}$skippedShows=$shows->count()-count($sources);if(empty($sources))return['updated'=>0,'skipped_shows'=>$skippedShows];
        try{$shipmentsByShow=$this->fetchShipmentsForShows($sources,$channelUsername,$debug);}catch(\Throwable $e){$message=$e->getMessage();if(str_contains($message,'CHANNEL_SWITCH_')||str_contains($message,'CHANNEL_CONTEXT_MISMATCH')||str_contains($message,'CHANNEL_SWITCH_FAILED'))throw$e;Log::error('WhatnotScraper: shipments refresh failed — '.$message);return['updated'=>0,'skipped_shows'=>$shows->count()];}
        $updated=0;foreach($shipmentsByShow as $showKey=>$rows){$show=$byKey[$showKey]??null;if(!$show||empty($rows))continue;$orderRes=$this->persistShowOrders($show,$rows);$updated+=$orderRes['updated']??0;$shipmentRes=$this->persistShipments($show,$rows);$updated+=$shipmentRes['created']??0;}return['updated'=>$updated,'skipped_shows'=>$skippedShows];
    }

    public function fetchOrdersForShows(array $sources,?string $channelUsername=null,bool $debug=false,?callable $onProgress=null):array{return $this->runBatchScrape('orders-batch',$sources,$channelUsername,$debug,$onProgress);}
    public function fetchShipmentsForShows(array $sources,?string $channelUsername=null,bool $debug=false,?callable $onProgress=null):array{return $this->runBatchScrape('shipments-batch',$sources,$channelUsername,$debug,$onProgress);}
    public function fetchShipmentsFromLivePage(?string $channelUsername=null,bool $debug=false,?callable $onProgress=null):array{return $this->runSimpleScrape('shipments-live',$channelUsername,$debug,$onProgress);}

    private function runBatchScrape(string $mode,array $sources,?string $channelUsername,bool $debug,?callable $onProgress=null):array
    {
        if(empty($sources))return[];$srcFile=tempnam(sys_get_temp_dir(),'wn-'.$mode.'-').'.json';file_put_contents($srcFile,json_encode(array_values($sources)));$env=$this->baseEnv($debug);$env['WHATNOT_MODE']=$mode;$env['WHATNOT_ORDER_SOURCES_FILE']=$srcFile;if($channelUsername)$env['WHATNOT_CHANNEL_NAME']=$channelUsername;
        // Pagination and detail enrichment can make even one busy show take more
        // than five minutes. Give every order/shipment batch a real 20-minute
        // floor, then scale by show count for larger batches, capped at one hour.
        $timeout=min(3600,max(1200,300+count($sources)*30));
        try{$process=$this->makeProcess($env,timeout:$timeout);$this->withBrowserLock(function()use($process,$onProgress){$onProgress?$this->streamProcess($process,$onProgress):$process->run();},waitSeconds:$timeout);$stderr=trim($process->getErrorOutput());$stdout=trim($process->getOutput());if($stderr){Log::channel('stack')->warning("WhatnotScraper {$mode} stderr",['output'=>$stderr]);if($debug)fwrite(STDERR,$stderr."\n");}$this->throwForExitCode((int)$process->getExitCode(),$stderr,$process->getCommandLine());if(!$process->isSuccessful())throw new \RuntimeException("Whatnot {$mode} scraper failed: ".($stderr?:"Scraper exited with code {$process->getExitCode()}"));if(empty($stdout))return[];$data=json_decode($stdout,true);if(json_last_error()!==JSON_ERROR_NONE||!is_array($data))throw new \RuntimeException("Whatnot {$mode} scraper returned invalid JSON: ".json_last_error_msg());$map=[];foreach($data as $entry){$key=$entry['show_key']??null;if($key===null)continue;$map[$key]=$entry['orders']??[];}return$map;}finally{@unlink($srcFile);}
    }

    private function runSimpleScrape(string $mode,?string $channelUsername,bool $debug,?callable $onProgress=null):array
    {
        $env=$this->baseEnv($debug);$env['WHATNOT_MODE']=$mode;if($channelUsername)$env['WHATNOT_CHANNEL_NAME']=$channelUsername;$timeout=3600;$process=$this->makeProcess($env,timeout:$timeout);$this->withBrowserLock(function()use($process,$onProgress){$onProgress?$this->streamProcess($process,$onProgress):$process->run();},waitSeconds:$timeout);$stderr=trim($process->getErrorOutput());$stdout=trim($process->getOutput());if($stderr){Log::channel('stack')->warning("WhatnotScraper {$mode} stderr",['output'=>$stderr]);if($debug)fwrite(STDERR,$stderr."\n");}$this->throwForExitCode((int)$process->getExitCode(),$stderr,$process->getCommandLine());if(!$process->isSuccessful())throw new \RuntimeException("Whatnot {$mode} scraper failed: ".($stderr?:"Scraper exited with code {$process->getExitCode()}"));if(empty($stdout))return[];$data=json_decode($stdout,true);if(json_last_error()!==JSON_ERROR_NONE||!is_array($data))throw new \RuntimeException("Whatnot {$mode} scraper returned invalid JSON: ".json_last_error_msg());$map=[];foreach($data as $entry){$key=$entry['live_id']??null;if($key!==null)$map[$key]=$entry['orders']??[];}return$map;
    }

    public function fetchLedger(string $from,string $to,?string $channelUsername=null,bool $debug=false):array
    {
        $env=$this->baseEnv($debug);$env['WHATNOT_MODE']='ledger';$env['WHATNOT_LEDGER_FROM']=$from;$env['WHATNOT_LEDGER_TO']=$to;if($channelUsername)$env['WHATNOT_CHANNEL_NAME']=$channelUsername;$process=$this->makeProcess($env,timeout:900);$this->withBrowserLock(fn()=>$process->run());$stderr=trim($process->getErrorOutput());$stdout=trim($process->getOutput());if($stderr){Log::channel('stack')->warning('WhatnotScraper ledger stderr',['output'=>$stderr]);if($debug)fwrite(STDERR,$stderr."\n");}$this->throwForExitCode((int)$process->getExitCode(),$stderr,$process->getCommandLine());if(!$process->isSuccessful())throw new \RuntimeException('Whatnot ledger scraper failed: '.($stderr?:"Scraper exited with code {$process->getExitCode()}"));if(empty($stdout))return[];$data=json_decode($stdout,true);if(json_last_error()!==JSON_ERROR_NONE||!is_array($data))throw new \RuntimeException('Whatnot ledger scraper returned invalid JSON: '.json_last_error_msg());return$data;
    }

    public function importLedger(?WhatnotChannel $channel,string $from,string $to,bool $debug=false):array
    {
        $cursor=Carbon::parse($from)->startOfDay();$end=Carbon::parse($to)->startOfDay();$created=0;$skipped=0;$windows=0;while($cursor->lte($end)){$wEnd=(clone$cursor)->addDays(30);if($wEnd->gt($end))$wEnd=clone$end;$windows++;$rows=$this->fetchLedger($cursor->toDateString(),$wEnd->toDateString(),$channel?->whatnot_username,$debug);foreach($rows as $row){if($this->persistLedgerRow($channel,$row))$created++;else$skipped++;}$cursor=(clone$wEnd)->addDay();}Log::info('WhatnotScraper importLedger complete',['channel'=>$channel?->name,'from'=>$from,'to'=>$to,'created'=>$created,'skipped'=>$skipped,'windows'=>$windows]);return compact('created','skipped','windows');
    }

    private function persistLedgerRow(?WhatnotChannel $channel,array $row):bool
    {
        $amountRaw=$row['amount']??null;$amount=($amountRaw!==null&&$amountRaw!=='')?(float)str_replace(['$',',',' '],'',$amountRaw):null;$dedup=md5(implode('|',[$channel?->id??'',$row['order_id']??'',$row['listing_id']??'',$row['created_date']??'',$amountRaw??'',$row['transaction_type']??'']));if(WhatnotLedgerEntry::where('dedup_key',$dedup)->exists())return false;WhatnotLedgerEntry::create(['whatnot_channel_id'=>$channel?->id,'created_date'=>$this->parseWnDateTime($row['created_date']??null),'completed_date'=>$this->parseWnDateTime($row['completed_date']??null),'amount'=>$amount,'listing_id'=>$row['listing_id']??null,'whatnot_order_id'=>$row['order_id']??null,'order_hash'=>$row['order_hash']??null,'message'=>$row['message']??null,'status'=>$row['status']??null,'transaction_type'=>$row['transaction_type']??null,'dedup_key'=>$dedup,'raw_data'=>$row]);return true;
    }
    private function parseWnDateTime(?string $s):?string{if(!$s)return null;try{return Carbon::parse($s)->toDateTimeString();}catch(\Throwable){return null;}}

    protected function makeProcess(array $env,int $timeout=180):Process
    {
        if(!isset($env['PLAYWRIGHT_BROWSERS_PATH'])){$pwPath=config('vortex.whatnot.playwright_browsers_path');$env['PLAYWRIGHT_BROWSERS_PATH']=$pwPath?:'/opt/pw-browsers';}
        if(!isset($env['PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH'])){$explicit=config('vortex.whatnot.playwright_chromium_executable');if($explicit)$env['PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH']=$explicit;else{$marker=storage_path('chromium-path.txt');if(file_exists($marker)){$markerPath=trim(file_get_contents($marker));if($markerPath&&file_exists($markerPath))$env['PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH']=$markerPath;}}}
        if(!isset($env['WHATNOT_PROXY'])&&$proxy=config('vortex.whatnot.proxy'))$env['WHATNOT_PROXY']=$proxy;if(!isset($env['WHATNOT_HEADLESS'])&&($headless=config('vortex.whatnot.headless'))!==null)$env['WHATNOT_HEADLESS']=$headless?'true':'false';$env['WHATNOT_BROWSER_BACKEND']=(string)config('vortex.whatnot.browser_backend','local');$env['STEEL_BASE_URL']=(string)config('vortex.whatnot.steel_base_url','http://127.0.0.1:3000');$process=new Process($this->commandLine($env),null,$env);$process->setTimeout($timeout);return$process;
    }

    private function commandLine(array $env):array
    {
        $command=[$this->nodeBin,$this->scriptPath];$headed=($env['WHATNOT_HEADLESS']??'true')==='false';$display=$env['DISPLAY']??getenv('DISPLAY');$hasDisplay=$display!==false&&$display!=='';if(!$headed||$hasDisplay)return$command;$wrapper=base_path('scripts/with-xvfb.sh');if(!is_readable($wrapper))return$command;return['/bin/sh',$wrapper,...$command];
    }

    private function streamProcess(Process $process,callable $onProgress):void
    {
        $process->start();while($process->isRunning()){$process->checkTimeout();if($err=$process->getIncrementalErrorOutput())foreach(explode("\n",trim($err))as$line)if($line!=='')$onProgress($line);usleep(200_000);}if($err=$process->getIncrementalErrorOutput())foreach(explode("\n",trim($err))as$line)if($line!=='')$onProgress($line);
    }

    protected function withBrowserLock(callable $fn,?int $waitSeconds=null)
    {
        $waitSeconds??=(int)config('vortex.whatnot.browser_lock_wait',1200);$lock=\App\Support\WhatnotBrowserLock::make();if(!$lock->get()){self::announceLockWait();try{$lock->block($waitSeconds);}catch(\Illuminate\Contracts\Cache\LockTimeoutException $e){throw new \RuntimeException('Timed out waiting for the shared Whatnot browser lock.',0,$e);}}try{return$fn();}finally{$lock->release();}
    }

    public static function announceLockWait():void
    {
        $holder=\App\Support\WhatnotBrowserLock::holder();$message=match(true){$holder===null=>'The browser lock looks free but could not be taken — another job took it in the same instant. Waiting.',$holder['host']!==gethostname()=>"The browser lock is held by PID {$holder['pid']} on {$holder['host']}.",$holder['alive']=>"Waiting for the shared browser lock — PID {$holder['pid']} is still scraping. This one will start when that finishes.",default=>"The browser lock holder PID {$holder['pid']} is stale; automatic recovery will run on the next acquisition."};Log::channel('stack')->warning('WhatnotScraper '.$message);if(PHP_SAPI==='cli'&&defined('STDERR'))fwrite(STDERR,"\n  ".$message."\n\n");
    }

    private function parseScrapedTimestamp(mixed $raw):Carbon{if(is_int($raw)||is_float($raw)||preg_match('/^\d{9,14}$/',(string)$raw)){$n=(float)$raw;return Carbon::createFromTimestampMs($n<1e11?$n*1000:$n);}return Carbon::parse($raw);}

    public function seedLiveIdFor(?WhatnotChannel $channel):?string
    {
        $uuid='/([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})/i';$fromShows=Show::query()->when($channel,fn($q)=>$q->where('whatnot_channel_id',$channel->id))->whereNotNull('detail_url')->orderByDesc('show_date')->limit(20)->pluck('detail_url');foreach($fromShows as$url)if(preg_match($uuid,(string)$url,$m))return$m[1];$onlyChannel=WhatnotChannel::count()===1;$fromLogs=ShowIngestionLog::query()->where('source','whatnot')->whereNotNull('raw_payload')->latest('id')->limit(50)->pluck('raw_payload');foreach($fromLogs as$payload){if(!is_array($payload))continue;$stamped=$payload['_channel_id']??null;$belongsHere=$stamped!==null?((int)$stamped===(int)$channel?->id):($onlyChannel||$channel===null);if($belongsHere&&preg_match($uuid,(string)($payload['detail_url']??''),$m))return$m[1];}return null;
    }

    public function importShows(?WhatnotChannel $channel=null,int $limit=50,bool $debug=false,bool $withOrders=true,?callable $onProgress=null,?string $seedLiveId=null):array
    {
        $seedLiveId??=$this->seedLiveIdFor($channel);if($seedLiveId&&$onProgress)$onProgress("Starting the analytics walk from a known show ({$seedLiveId})");try{$rows=$this->fetchShows($limit,$debug,$channel?->whatnot_username,$onProgress,$seedLiveId);}catch(\Throwable$e){ShowIngestionLog::create(['whatnot_channel_id'=>$channel?->id,'source'=>'whatnot','status'=>'failed','error_message'=>$e->getMessage(),'raw_payload'=>['channel'=>$channel?->name,'limit'=>$limit]]);throw$e;}$created=0;$updated=0;$skipped=0;if($onProgress)$onProgress(sprintf('Fetched %d show(s) from Whatnot — importing…',count($rows)));$orderTargets=[];
        foreach($rows as$row){$row['_channel_id']=$channel?->id;if(empty($row['title'])&&empty($row['show_date'])){$skipped++;ShowIngestionLog::create(['whatnot_channel_id'=>$channel?->id,'source'=>'whatnot','status'=>'failed','error_message'=>'Scraped row had no title or show_date — could not identify the show.','raw_payload'=>$row]);if($onProgress)$onProgress('Skipped: row had no title or show date');continue;}$lookupTitle=$row['title']?trim($row['title']):null;$lookupDate=$row['show_date']??null;if(!$lookupDate&&filled($row['show_date_raw']??null))try{$parsed=$this->parseScrapedTimestamp($row['show_date_raw']);$lookupDate=$parsed->toDateString();$row['show_date']=$lookupDate;$row['start_time']??=$parsed->format('H:i:s');}catch(\Throwable){}$query=Show::query()->where('import_source','auto_whatnot');if($lookupTitle&&$lookupDate)$query->where('title',$lookupTitle)->whereDate('show_date',$lookupDate);elseif($lookupTitle)$query->where('title',$lookupTitle);elseif($lookupDate)$query->whereDate('show_date',$lookupDate);else{$skipped++;continue;}$existing=$query->first();$payload=array_filter(['whatnot_channel_id'=>$channel?->id,'title'=>$lookupTitle,'show_date'=>$lookupDate,'start_time'=>$row['start_time']??null,'end_time'=>$row['end_time']??null,'show_duration'=>$row['show_duration']??null,'gross_revenue'=>$row['gross_revenue']??null,'whatnot_net'=>$row['whatnot_net']??null,'tips'=>$row['tips']??null,'units_sold'=>$row['units_sold']??null,'detail_url'=>$row['detail_url']??null,'completed_earnings'=>$row['completed_earnings']??null,'avg_order_value'=>$row['avg_order_value']??null,'giveaway_spend'=>$row['giveaway_spend']??null,'giveaways_count'=>$row['giveaways_count']??null,'buyers_count'=>$row['buyers_count']??null,'first_time_buyers'=>$row['first_time_buyers']??null,'returning_buyers'=>$row['returning_buyers']??null,'shares_count'=>$row['shares_count']??null,'max_concurrent_viewers'=>$row['max_concurrent_viewers']??null,'total_views'=>$row['total_views']??null,'avg_order_rating'=>$row['avg_order_rating']??null,'import_source'=>'auto_whatnot'],fn($v)=>$v!==null);
            if($existing){$updateFields=array_intersect_key($payload,array_flip(['gross_revenue','whatnot_net','tips','units_sold','show_duration','detail_url','start_time','end_time','completed_earnings','avg_order_value','giveaway_spend','giveaways_count','buyers_count','first_time_buyers','returning_buyers','shares_count','max_concurrent_viewers','total_views','avg_order_rating']));if($channel&&$existing->whatnot_channel_id&&(int)$existing->whatnot_channel_id!==(int)$channel->id)$updateFields['channel_attribution_suspect']=true;if(in_array($existing->status,['pending_approval','reconciled','closed'],true)){$changes=[];foreach(['gross_revenue','whatnot_net','tips','units_sold']as$field){if(!array_key_exists($field,$updateFields))continue;$old=(float)$existing->{$field};$new=(float)$updateFields[$field];if(abs($old-$new)>0.01)$changes[]="{$field}: {$old} → {$new}";}if(!empty($changes)){$updateFields['financials_revised_after_lock']=true;$updateFields['revision_notes']=trim(($existing->revision_notes?$existing->revision_notes."\n":'').now()->format('M j, Y g:ia').' — '.implode('; ',$changes));}}if(!empty($updateFields)){$existing->trackChanges($updateFields,'whatnot_import');$existing->update($updateFields);$updated++;ShowIngestionLog::create(['show_id'=>$existing->id,'whatnot_channel_id'=>$existing->whatnot_channel_id??$channel?->id,'source'=>'whatnot','status'=>'success','raw_payload'=>$row]);if($onProgress)$onProgress("Updated: \"{$existing->title}\" ({$lookupDate}) — ".implode(', ',array_keys($updateFields)));}else{$skipped++;if($onProgress)$onProgress("Unchanged: \"{$existing->title}\" ({$lookupDate}) — already up to date");}$showModel=$existing;if($showModel->streamers()->count()===0)$showModel->detectStreamers();}
            else{if(!$lookupDate){$skipped++;continue;}$status=($payload['units_sold']??0)>0?'mapping':'draft';$show=Show::create(array_merge($payload,['status'=>$status,'created_by'=>auth()->id()??1]));$show->detectStreamers();$created++;$showModel=$show;ShowIngestionLog::create(['show_id'=>$show->id,'whatnot_channel_id'=>$show->whatnot_channel_id??$channel?->id,'source'=>'whatnot','status'=>'success','raw_payload'=>$row]);if($onProgress)$onProgress("Created: \"{$show->title}\" ({$lookupDate})");}$this->ensureStreamerLogEntry($showModel);$liveId=$row['whatnot_live_id']??$this->extractLiveIdFromUrl($row['detail_url']??null);if($withOrders&&$liveId)$orderTargets[]=['show'=>$showModel,'live_id'=>$liveId];}
        if($onProgress)$onProgress("Shows done: {$created} created, {$updated} updated, {$skipped} skipped.");$ordersCreated=0;$shipmentsCreated=0;if($withOrders&&!empty($orderTargets)){if($onProgress)$onProgress(sprintf('Scraping orders for %d show(s)…',count($orderTargets)));$ordersCreated=$this->importOrdersForTargets($orderTargets,$channel?->whatnot_username,$debug,$onProgress);if($onProgress)$onProgress("Orders done: {$ordersCreated} order(s) created.");if($onProgress)$onProgress(sprintf('Scraping shipments for %d show(s)…',count($orderTargets)));$showsWithOrders=collect($orderTargets)->pluck('show')->unique('id');$shipmentResults=$this->refreshShipmentsForShows($showsWithOrders,$channel?->whatnot_username,$debug);$shipmentsCreated=$shipmentResults['updated']??0;if($onProgress)$onProgress("Shipments done: {$shipmentsCreated} shipment(s) imported.");}Log::info('WhatnotScraper import complete',['channel'=>$channel?->name,'created'=>$created,'updated'=>$updated,'skipped'=>$skipped,'orders_created'=>$ordersCreated,'shipments_created'=>$shipmentsCreated]);return compact('created','updated','skipped','ordersCreated','shipmentsCreated');
    }

    private function ensureStreamerLogEntry(Show $show):void{$show->loadMissing('streamers');$streamer=$show->primaryStreamer();if(!$streamer)return;StreamerLogEntry::firstOrCreate(['show_id'=>$show->id],['streamer_id'=>$streamer->id,'status'=>'pending','gross_revenue'=>$show->gross_revenue]);}
    private function extractLiveIdFromUrl(?string $url):?string{if(!$url)return null;if(preg_match('/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i',$url,$m))return$m[0];return null;}

    private function importOrdersForTargets(array $targets,?string $channelUsername,bool $debug,?callable $onProgress=null):int
    {
        $sources=[];$byKey=[];foreach($targets as$t){$key=$t['show']->id;$sources[]=['live_id'=>$t['live_id'],'show_key'=>$key];$byKey[$key]=$t['show'];}try{$ordersByShow=$this->fetchOrdersForShows($sources,$channelUsername,$debug,$onProgress);}catch(\Throwable$e){Log::error('WhatnotScraper: batched order scrape failed — '.$e->getMessage());return 0;}$ordersCreated=0;foreach($ordersByShow as$showKey=>$rows){$show=$byKey[$showKey]??null;if(!$show||empty($rows))continue;$expected=(int)($show->units_sold??0);if($expected>0&&count($rows)>($expected*2+100)){Log::warning('WhatnotScraper: order count far exceeds expected — skipping (likely unfiltered)',['show_id'=>$show->id,'scraped'=>count($rows),'expected'=>$expected]);continue;}$res=$this->persistShowOrders($show,$rows);$ordersCreated+=$res['created'];if($onProgress)$onProgress("Orders for \"{$show->title}\": {$res['created']} created, {$res['skipped']} skipped".(($res['updated']??0)>0?", {$res['updated']} updated":''));}return$ordersCreated;
    }

    public function importAllEnabledChannels(int $limit=50,bool $debug=false,bool $withOrders=true):array
    {
        $channels=WhatnotChannel::where('include_in_import',true)->where('status','active')->get();if($channels->isEmpty())return['created'=>0,'updated'=>0,'skipped'=>0,'channels'=>0];$totals=['created'=>0,'updated'=>0,'skipped'=>0,'errors'=>[]];foreach($channels as$channel)try{$result=$this->importShows($channel,$limit,$debug,$withOrders);$totals['created']+=$result['created'];$totals['updated']+=$result['updated'];$totals['skipped']+=$result['skipped'];}catch(\RuntimeException$e){Log::error("WhatnotScraper: channel \"{$channel->name}\" failed — {$e->getMessage()}");$totals['errors'][]="Channel \"{$channel->name}\": {$e->getMessage()}";}$totals['channels']=$channels->count();return$totals;
    }

    public function runDiscover(?WhatnotChannel $channel=null,bool $debug=true,?callable $onProgress=null):array{$env=$this->baseEnv($debug);$env['WHATNOT_MODE']='discover';if($channel?->whatnot_username)$env['WHATNOT_CHANNEL_NAME']=$channel->whatnot_username;$process=$this->makeProcess($env,timeout:600);$this->withBrowserLock(function()use($process,$onProgress){$onProgress?$this->streamProcess($process,$onProgress):$process->run();});$stderr=trim($process->getErrorOutput());$stdout=trim($process->getOutput());if($stderr)Log::channel('stack')->info('WhatnotScraper discover stderr',['output'=>$stderr]);if(!$process->isSuccessful())throw new \RuntimeException('Discover failed: '.($stderr?:"exit {$process->getExitCode()}"));$envelope=json_decode($stdout,true);if(isset($envelope['output_file'])&&file_exists($envelope['output_file'])){$data=json_decode(file_get_contents($envelope['output_file']),true);@unlink($envelope['output_file']);}else$data=json_decode($stdout,true);if(json_last_error()!==JSON_ERROR_NONE)throw new \RuntimeException('Discover returned invalid JSON: '.substr($stdout,0,300));return$data;}
    public function runWsExplore(?WhatnotChannel $channel=null,?callable $onProgress=null):array{$env=$this->baseEnv(true);$env['WHATNOT_MODE']='ws-explore';if($channel?->whatnot_username)$env['WHATNOT_CHANNEL_NAME']=$channel->whatnot_username;$process=$this->makeProcess($env,timeout:120);$this->withBrowserLock(function()use($process,$onProgress){$onProgress?$this->streamProcess($process,$onProgress):$process->run();});$stdout=trim($process->getOutput());$stderr=trim($process->getErrorOutput());if(!$process->isSuccessful())throw new \RuntimeException('ws-explore failed: '.($stderr?:"exit {$process->getExitCode()}"));$envelope=json_decode($stdout,true);if(isset($envelope['output_file'])&&file_exists($envelope['output_file'])){$data=json_decode(file_get_contents($envelope['output_file']),true);@unlink($envelope['output_file']);}else$data=json_decode($stdout,true);return$data;}
    public function cookiesFilePath():string{return storage_path('whatnot-cookies.json');}public function hasCookieFile():bool{return file_exists($this->cookiesFilePath());}
    public function probePathsInBrowser(array $urls,bool $debug=false,bool $soft=false):array{$env=$this->baseEnv($debug);$env['WHATNOT_MODE']='path-probe';$env['WHATNOT_PROBE_URLS']=implode(',',$urls);$env['WHATNOT_PROBE_SOFT']=$soft?'1':'0';$process=$this->makeProcess($env,timeout:90+count($urls)*35);$this->withBrowserLock(fn()=>$process->run());$stdout=trim($process->getOutput());$stderr=trim($process->getErrorOutput());if(!$process->isSuccessful())throw new \RuntimeException('Path probe failed: '.($stderr?:"exit {$process->getExitCode()}"));$data=json_decode($stdout,true);if(!is_array($data)||!isset($data['results']))throw new \RuntimeException('Path probe returned unexpected response: '.$stdout);return['navigations'=>$data['results'],'fetches'=>$data['soft']??[]];}
    public function discoverApi(bool $debug=false,?string $find=null):array{$env=$this->baseEnv($debug);$env['WHATNOT_MODE']='api-discover';if(filled($find))$env['WHATNOT_FIND']=$find;$process=$this->makeProcess($env,timeout:900);$this->withBrowserLock(fn()=>$process->run());$stdout=trim($process->getOutput());$stderr=trim($process->getErrorOutput());if(!$process->isSuccessful())throw new \RuntimeException('API discovery failed: '.($stderr?:"exit {$process->getExitCode()}"));$data=json_decode($stdout,true);if(!is_array($data))throw new \RuntimeException('API discovery returned unexpected response: '.$stdout);return['operations'=>$data['operations']??[],'liveCalls'=>$data['liveCalls']??[],'introspection'=>$data['introspection']??null,'scriptCount'=>$data['scriptCount']??0,'needle'=>$data['needle']??null,'needleHits'=>$data['needleHits']??[],'chunksScanned'=>$data['chunksScanned']??0,'buildId'=>$data['buildId']??null,'landedOn'=>$data['landedOn']??null,'landingStatus'=>$data['landingStatus']??null];}
    public function testCookieAuth():array{$process=$this->makeProcess(['WHATNOT_MODE'=>'cookie-test','WHATNOT_DEBUG'=>'0'],timeout:120);try{$this->withBrowserLock(fn()=>$process->run());}catch(\Symfony\Component\Process\Exception\ProcessTimedOutException$e){throw new \RuntimeException('The browser did not finish starting within 120s, so the session was never actually tested.');}$stdout=trim($process->getOutput());$stderr=trim($process->getErrorOutput());if(!$process->isSuccessful())throw new \RuntimeException('Cookie auth test failed: '.($stderr?:"exit {$process->getExitCode()}"));$data=json_decode($stdout,true);if(!$data||empty($data['ok']))throw new \RuntimeException('Cookie auth test returned unexpected response: '.$stdout);return$data;}
    public function dumpSessionCookies():int{[$email,$password]=$this->resolveCredentials();$process=$this->makeProcess(['WHATNOT_EMAIL'=>$email,'WHATNOT_PASSWORD'=>$password,'WHATNOT_MODE'=>'dump-cookies'],timeout:60);$this->withBrowserLock(fn()=>$process->run());if(!$process->isSuccessful())throw new \RuntimeException('Cookie dump failed: '.($process->getErrorOutput()?:"exit {$process->getExitCode()}"));$json=json_decode($process->getOutput(),true);if(!is_array($json))throw new \RuntimeException('Cookie dump returned invalid JSON');file_put_contents($this->cookiesFilePath(),json_encode($json,JSON_PRETTY_PRINT));return count($json);}

    protected function throwForExitCode(int $exitCode,string $stderr,?string $commandLine=null):void
    {
        $diagnostics=implode("\n",array_slice(array_values(array_filter(explode("\n",$stderr),fn($l)=>trim($l)!=='')),0,80));if($diagnostics==='')$diagnostics="The scraper exited {$exitCode} without diagnostics. Command: ".($commandLine??'(not recorded)');if($exitCode===self::EXIT_AUTH_REQUIRED)throw new \App\Exceptions\WhatnotBlockedException("Whatnot returned an authentication/challenge page.\n".$diagnostics);if($exitCode===self::EXIT_RATE_LIMITED)throw new \App\Exceptions\WhatnotBlockedException("Whatnot is rate limiting this account. Wait and retry.\n\n".$diagnostics);if($exitCode===self::EXIT_SELECTOR_MISS)throw new \RuntimeException("Whatnot scraper: page selectors didn't match.\n\n".$diagnostics);
    }

    private function baseEnv(bool $debug=false):array{$env=['WHATNOT_DEBUG'=>$debug?'1':'0'];$email=config('vortex.whatnot.email');$password=config('vortex.whatnot.password');if($email)$env['WHATNOT_EMAIL']=$email;if($password)$env['WHATNOT_PASSWORD']=$password;if(($cookiesFile=getenv('WHATNOT_COOKIES_FILE'))!==false&&$cookiesFile!=='')$env['WHATNOT_COOKIES_FILE']=$cookiesFile;return$env;}
    private function resolveCredentials():array{$email=config('vortex.whatnot.email');$password=config('vortex.whatnot.password');if(!$email||!$password)throw new \RuntimeException('WHATNOT_EMAIL and WHATNOT_PASSWORD are not set. Add them to your .env file.');return[$email,$password];}
}
