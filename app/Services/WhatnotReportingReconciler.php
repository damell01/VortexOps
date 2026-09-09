<?php

namespace App\Services;

use App\Models\Shipment;
use App\Models\Show;
use App\Models\WhatnotBuyer;
use App\Models\WhatnotChannel;
use App\Models\WhatnotShowOrder;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WhatnotReportingReconciler
{
    public function __construct(private readonly WhatnotScraper $scraper, private readonly WhatnotDataNormalizer $normalizer) {}

    public function reconcileOrders(WhatnotChannel $channel, Carbon $since, int $batchSize = 25, ?callable $progress = null): array
    {
        $batchSize = max(1, min(30, $batchSize));
        $shows = Show::query()->where('whatnot_channel_id', $channel->id)
            ->whereDate('show_date', '>=', $since->toDateString())->whereDate('show_date', '<=', today())
            ->whereNotIn('status', ['cancelled'])->whereNotNull('whatnot_show_id')->withCount('orders')
            ->orderByRaw('CASE WHEN (SELECT COUNT(*) FROM whatnot_show_orders WHERE whatnot_show_orders.show_id = shows.id) > 5000 THEN 0 WHEN (SELECT COUNT(*) FROM whatnot_show_orders WHERE whatnot_show_orders.show_id = shows.id) = 0 THEN 1 ELSE 2 END')
            ->orderBy('show_date')->orderBy('id')->get();
        $checked=$replaced=$created=$rejected=$skipped=0;
        foreach ($shows->chunk($batchSize) as $chunk) {
            $sources=[];$byKey=[];
            foreach ($chunk as $show) {
                $liveId=$this->liveId($show); if(!$liveId){$skipped++;continue;}
                $sources[]=['live_id'=>$liveId,'show_key'=>$show->id];$byKey[(string)$show->id]=$show;
            }
            if($sources===[])continue;
            $progress&&$progress('orders: scraping '.count($sources).' show(s) as one verified channel batch');
            $rowsByShow=$this->scraper->fetchOrdersForShows($sources,$channel->whatnot_username,false,$progress);
            foreach($sources as $source){$key=(string)$source['show_key'];$show=$byKey[$key]??null;if(!$show)continue;$checked++;
                if(!array_key_exists($key,$rowsByShow)&&!array_key_exists((int)$key,$rowsByShow)){$rejected++;$progress&&$progress("orders: show #{$show->id} rejected — scraper returned no keyed result");continue;}
                $rows=$rowsByShow[$key]??$rowsByShow[(int)$key]??[];
                if(!is_array($rows)||!$this->orderBatchLooksPlausible($show,$rows)){$rejected++;$progress&&$progress("orders: show #{$show->id} rejected — implausible row count ".(is_array($rows)?count($rows):0));continue;}
                $before=WhatnotShowOrder::where('show_id',$show->id)->count();
                $result=DB::transaction(function()use($show,$rows){WhatnotShowOrder::where('show_id',$show->id)->delete();$result=$this->scraper->persistShowOrders($show,$rows);$show->updateQuietly(['last_synced_at'=>now()]);return $result;});
                $replaced+=$before;$created+=(int)($result['created']??0);$progress&&$progress("orders: show #{$show->id} reconciled — {$before} old row(s) replaced with ".(int)($result['created']??0));
            }
        }
        $buyers=$this->rebuildBuyers($channel,$since);$progress&&$progress("buyers: {$buyers['created']} created, {$buyers['updated']} updated");
        return compact('checked','replaced','created','rejected','skipped','buyers');
    }

    public function backfillAnalytics(WhatnotChannel $channel, Carbon $since, int $limit=25, ?callable $progress=null): array
    {
        $limit=max(1,min(25,$limit));
        $missing=fn()=>Show::query()->where('whatnot_channel_id',$channel->id)->whereDate('show_date','>=',$since->toDateString())->whereDate('show_date','<=',today())->whereNotIn('status',['cancelled'])->whereNotNull('whatnot_show_id')->where(fn($q)=>$q->whereNull('gross_revenue')->orWhere('gross_revenue','<=',0)->orWhereNull('whatnot_net')->orWhere('whatnot_net','<=',0));
        $recentSlots=min($limit,max(1,(int)ceil($limit/2)));$recent=$missing()->orderByDesc('show_date')->orderByDesc('id')->limit($recentSlots)->get();$remaining=$limit-$recent->count();$older=collect();
        if($remaining>0)$older=$missing()->when($recent->isNotEmpty(),fn($q)=>$q->whereNotIn('id',$recent->pluck('id')))->orderBy('show_date')->orderBy('id')->limit($remaining)->get();
        $shows=$recent->concat($older)->values();$updated=$failed=$skipped=0;
        foreach($shows as $show){$liveId=$this->liveId($show);if(!$liveId){$skipped++;continue;}
            try{$progress&&$progress("analytics: refreshing show #{$show->id} ({$show->show_date})");$rawRows=$this->scraper->fetchShows(limit:1,debug:false,channelUsername:$channel->whatnot_username,onProgress:$progress,seedLiveId:$liveId);
                $raw=collect($rawRows)->first(function($row)use($liveId){if(!is_array($row))return false;$candidate=strtolower((string)($row['whatnot_live_id']??$row['live_id']??''));return $candidate===''||$candidate===strtolower($liveId);});if(!is_array($raw))throw new \RuntimeException('No analytics row returned for show UUID.');
                $normalized=$this->normalizer->normalizeShow($raw);$fields=[];foreach(['gross_revenue','whatnot_net','completed_earnings','avg_order_value','giveaway_spend','units_sold','giveaways_count','buyers_count','first_time_buyers','returning_buyers','shares_count','max_concurrent_viewers','total_views','show_duration']as$field)if(($normalized[$field]??null)!==null)$fields[$field]=$normalized[$field];if($fields===[])throw new \RuntimeException('Analytics loaded but contained no usable metrics.');
                $fields['last_synced_at']=now();$fields['last_analytics_synced_at']=now();$fields['raw_import_payload']=$raw;$show->forceFill($fields)->save();$updated++;$progress&&$progress("analytics: show #{$show->id} updated");
            }catch(\Throwable $e){$failed++;$progress&&$progress("analytics: show #{$show->id} failed — {$e->getMessage()}");Log::warning('Coordinated Whatnot analytics backfill failed',['show_id'=>$show->id,'channel'=>$channel->whatnot_username,'exception'=>$e->getMessage()]);}}
        return compact('updated','failed','skipped');
    }

    public function reconcileShipments(WhatnotChannel $channel,Carbon $since,int $batchSize=25,?callable $progress=null):array
    {$batchSize=max(1,min(30,$batchSize));$shows=Show::query()->where('whatnot_channel_id',$channel->id)->whereDate('show_date','>=',$since->toDateString())->whereDate('show_date','<=',today())->whereNotIn('status',['cancelled'])->whereNotNull('whatnot_show_id')->orderBy('show_date')->orderBy('id')->get();$checked=$created=$updated=$skipped=0;
        foreach($shows->chunk($batchSize)as$chunk){$before=Shipment::whereIn('show_id',$chunk->pluck('id'))->count();$progress&&$progress("shipments: scraping {$chunk->count()} show(s)");$result=$this->scraper->refreshShipmentsForShows($chunk,$channel->whatnot_username);$after=Shipment::whereIn('show_id',$chunk->pluck('id'))->count();$created+=max(0,$after-$before);$updated+=max(0,(int)($result['updated']??0)-max(0,$after-$before));$skipped+=(int)($result['skipped_shows']??0);$checked+=$chunk->count();}return compact('checked','created','updated','skipped');}

    private function rebuildBuyers(WhatnotChannel $channel,Carbon $since):array
    {$usernames=WhatnotShowOrder::query()->join('shows','whatnot_show_orders.show_id','=','shows.id')->where('shows.whatnot_channel_id',$channel->id)->whereDate('shows.show_date','>=',$since->toDateString())->whereNotNull('whatnot_show_orders.buyer_username')->where('whatnot_show_orders.buyer_username','<>','')->distinct()->pluck('whatnot_show_orders.buyer_username');$created=$updated=0;
        foreach($usernames as$username){$agg=WhatnotShowOrder::query()->join('shows','whatnot_show_orders.show_id','=','shows.id')->where('shows.whatnot_channel_id',$channel->id)->whereDate('shows.show_date','>=',$since->toDateString())->where('whatnot_show_orders.buyer_username',$username)->selectRaw('COUNT(*) as total_orders, SUM(whatnot_show_orders.total_price) as lifetime_spend, MIN(whatnot_show_orders.show_date) as first_purchase_date, MAX(whatnot_show_orders.show_date) as last_purchase_date, MAX(whatnot_show_orders.buyer_display_name) as display_name')->first();$totalOrders=(int)($agg->total_orders??0);$lifetimeSpend=(float)($agg->lifetime_spend??0);$attrs=['total_orders'=>$totalOrders,'lifetime_spend'=>$lifetimeSpend,'avg_order_value'=>$totalOrders>0?round($lifetimeSpend/$totalOrders,2):null,'first_purchase_date'=>$agg->first_purchase_date??null,'last_purchase_date'=>$agg->last_purchase_date??null,'display_name'=>($agg->display_name??null)?:null];$buyer=WhatnotBuyer::where('username',$username)->first();if($buyer){$buyer->update($attrs);$updated++;}else{WhatnotBuyer::create(['username'=>$username]+$attrs);$created++;}}
        if($usernames->isNotEmpty())DB::statement('UPDATE whatnot_show_orders o JOIN whatnot_buyers b ON b.username = o.buyer_username SET o.whatnot_buyer_id = b.id WHERE o.whatnot_buyer_id IS NULL AND o.buyer_username IS NOT NULL');return compact('created','updated');}

    private function orderBatchLooksPlausible(Show $show,array $rows):bool{$count=count($rows);if($count>5000)return false;$units=(int)($show->units_sold??0);if($units>0&&$count>max(500,($units*3)+100))return false;$ids=[];foreach($rows as$row){if(!is_array($row))return false;$id=trim((string)($row['order_id']??''));if($id!=='')$ids[]=$id;}if(count($ids)>20&&count(array_unique($ids))<(int)floor(count($ids)*0.8))return false;return true;}
    private function liveId(Show $show):?string{foreach([$show->whatnot_show_id,$show->detail_url]as$value)if(preg_match('/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i',(string)$value,$m))return strtolower($m[0]);return null;}
}
