<?php

namespace App\Models;

use App\Models\Concerns\AuditsUpdates;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Show extends Model
{
    use LogsActivity, AuditsUpdates;

    protected static array $doNotRecordEvents = ['updated'];

    public function auditableFields(): array
    {
        return ['status'];
    }

    protected $fillable = [
        'whatnot_channel_id','channel_attribution_suspect','financials_revised_after_lock','revision_notes','whatnot_show_id','cover_image_url','title','show_date','start_time','end_time','units_sold','gross_revenue','whatnot_net','fulfillment_notes','is_slow_pack','show_format','whatnot_fees','whatnot_payout_amount','last_synced_at','tips','paper_sales_gross','paper_sales_units','paper_sales_notes','sales_reconciled','show_duration','import_source','detail_url','completed_earnings','avg_order_value','giveaway_spend','giveaways_count','buyers_count','first_time_buyers','returning_buyers','shares_count','max_concurrent_viewers','total_views','avg_order_rating','raw_import_payload','ai_streamer_suggestion','status','status_changed_at','notes','created_by','shipping_surcharge_count','shipping_surcharge_total',
    ];

    protected $casts = [
        'show_date'=>'date','start_time'=>'datetime','end_time'=>'datetime','status_changed_at'=>'datetime','channel_attribution_suspect'=>'boolean','financials_revised_after_lock'=>'boolean','gross_revenue'=>'decimal:2','whatnot_net'=>'decimal:2','is_slow_pack'=>'boolean','whatnot_fees'=>'decimal:2','whatnot_payout_amount'=>'decimal:2','last_synced_at'=>'datetime','tips'=>'decimal:2','paper_sales_gross'=>'decimal:2','sales_reconciled'=>'boolean','raw_import_payload'=>'array','ai_streamer_suggestion'=>'array','units_sold'=>'integer','show_duration'=>'integer','completed_earnings'=>'decimal:2','avg_order_value'=>'decimal:2','giveaway_spend'=>'decimal:2','avg_order_rating'=>'decimal:2','giveaways_count'=>'integer','buyers_count'=>'integer','first_time_buyers'=>'integer','returning_buyers'=>'integer','shares_count'=>'integer','max_concurrent_viewers'=>'integer','total_views'=>'integer','shipping_surcharge_count'=>'integer','shipping_surcharge_total'=>'decimal:2',
    ];

    private ?array $profitAndLossCache = null;

    public function profitAndLoss(): array
    {
        if ($this->profitAndLossCache !== null) return $this->profitAndLossCache;
        $gross=(float)$this->gross_revenue; $net=(float)$this->whatnot_net; $tips=(float)$this->tips;
        $dr=$this->relationLoaded('latestDeductionRequest')?$this->getRelation('latestDeductionRequest'):$this->latestDeductionRequest()->with('lines')->first();
        $cogs=0.0; if($dr){$cogs=(float)($dr->relationLoaded('lines')?$dr->lines->sum('line_total'):$dr->lines()->sum('line_total'));}
        $payouts=$this->payouts_sum_calculated_payout!==null?(float)$this->payouts_sum_calculated_payout:(float)$this->payouts()->sum('calculated_payout');
        $base=$net+$tips; $margin=round($base-$cogs-$payouts,2);
        return $this->profitAndLossCache=['gross'=>round($gross,2),'net'=>round($net,2),'tips'=>round($tips,2),'cogs'=>round($cogs,2),'payouts'=>round($payouts,2),'margin'=>$margin,'margin_pct'=>$base>0?round($margin/$base*100,1):0.0];
    }

    public function getNetProfitAttribute(): float { return $this->profitAndLoss()['margin']; }

    public function engagement(): array
    {
        $peak=(int)($this->max_concurrent_viewers??0);$views=(int)($this->total_views??0);$buyers=(int)($this->buyers_count??0);$firstTime=(int)($this->first_time_buyers??0);$returning=(int)($this->returning_buyers??0);$denominator=$peak>0?$peak:$views;
        return ['peak_viewers'=>$peak,'total_views'=>$views,'shares'=>(int)($this->shares_count??0),'buyers'=>$buyers,'first_time'=>$firstTime,'returning'=>$returning,'rating'=>$this->avg_order_rating!==null?(float)$this->avg_order_rating:null,'conversion_pct'=>$denominator>0?round($buyers/$denominator*100,1):null,'has_data'=>($peak+$views+$buyers+$firstTime+$returning)>0];
    }

    protected static function booted(): void
    {
        static::$priorRevenuesCache=[];
        static::creating(function(Show $show){$show->status_changed_at??=now();});
        static::updating(function(Show $show){if($show->isDirty('status'))$show->status_changed_at=now();});
    }

    public function getActivitylogOptions(): LogOptions { return LogOptions::defaults()->logAll()->logOnlyDirty(); }
    public function channel(): BelongsTo { return $this->belongsTo(WhatnotChannel::class,'whatnot_channel_id'); }
    public function scopeInChannelContext(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder { return \App\Support\ChannelContext::isScoped()?$query->where('whatnot_channel_id',\App\Support\ChannelContext::currentId()):$query; }
    public function streamers(): BelongsToMany { return $this->belongsToMany(Streamer::class,'show_streamer')->withPivot('is_primary')->withTimestamps(); }
    public function primaryStreamer(): ?Streamer { return $this->streamers()->wherePivot('is_primary',true)->first(); }
    public function fulfillmentUsers(): BelongsToMany { return $this->belongsToMany(User::class,'show_fulfillment_user')->withTimestamps(); }
    public function fulfillmentPackages(): HasMany { return $this->hasMany(FulfillmentPackage::class); }
    public function deductionRequests(): HasMany { return $this->hasMany(DeductionRequest::class); }
    public function latestDeductionRequest(): HasOne { return $this->hasOne(DeductionRequest::class)->latestOfMany(); }
    public function ingestionLogs(): HasMany { return $this->hasMany(ShowIngestionLog::class); }
    public function payouts(): HasMany { return $this->hasMany(Payout::class); }
    public function orders(): HasMany { return $this->hasMany(WhatnotShowOrder::class); }

    public function itemReconciliation(): array
    {
        $entry=$this->relationLoaded('streamerLogEntry')?$this->streamerLogEntry:$this->streamerLogEntry()->first();
        $logged=$entry?$entry->items()->selectRaw('disposition, SUM(quantity) as total')->groupBy('disposition')->pluck('total','disposition'):collect();
        $orderUnits=(int)$this->orders()->sum('quantity');$whatnotSold=$orderUnits>0?$orderUnits:(int)($this->units_sold??0);
        $rows=[['key'=>'sold','label'=>'Sold','whatnot'=>$whatnotSold,'logged'=>(int)($logged['sold']??0)],['key'=>'giveaway','label'=>'Giveaways','whatnot'=>$this->giveaways_count===null?null:(int)$this->giveaways_count,'logged'=>(int)($logged['giveaway']??0)],['key'=>'promo','label'=>'Promo / Bonus','whatnot'=>null,'logged'=>(int)($logged['promo']??0)],['key'=>'other','label'=>'Other','whatnot'=>null,'logged'=>(int)($logged['other']??0)]];
        return array_map(fn(array $row)=>$row+['difference'=>$row['whatnot']===null?null:$row['logged']-$row['whatnot']],$rows);
    }

    public function whatnotAnalytics(): array
    {
        $money=function($value):?string{if($value===null)return null;$value=(float)$value;return($value<0?'-$':'$').number_format(abs($value),2);};$count=fn($value)=>$value===null?null:number_format((int)$value);$duration=function($minutes):?string{if($minutes===null)return null;$minutes=(int)$minutes;return $minutes>=60?intdiv($minutes,60).'h '.($minutes%60).'m':$minutes.'m';};
        return ['Sales'=>[['label'=>'Estimated sales','value'=>$money($this->gross_revenue),'hint'=>'Before Whatnot takes its cut'],['label'=>'Total est. earnings','value'=>$money($this->whatnot_net),'hint'=>'What Whatnot expects to pay out'],['label'=>'Completed earnings','value'=>$money($this->completed_earnings),'hint'=>'Settled so far'],['label'=>'Orders','value'=>$count($this->units_sold),'hint'=>null],['label'=>'Average order value','value'=>$money($this->avg_order_value),'hint'=>null],['label'=>'Giveaway spend','value'=>$money($this->giveaway_spend),'hint'=>null],['label'=>'Giveaways','value'=>$count($this->giveaways_count),'hint'=>null],['label'=>'Tips','value'=>$money($this->tips),'hint'=>null]],'Audience'=>[['label'=>'Buyers','value'=>$count($this->buyers_count),'hint'=>null],['label'=>'First-time buyers','value'=>$count($this->first_time_buyers),'hint'=>'Bought from this channel for the first time'],['label'=>'Returning buyers','value'=>$count($this->returning_buyers),'hint'=>null],['label'=>'Shares','value'=>$count($this->shares_count),'hint'=>null],['label'=>'Max concurrent viewers','value'=>$count($this->max_concurrent_viewers),'hint'=>'Most people watching at once'],['label'=>'Total views','value'=>$count($this->total_views),'hint'=>null],['label'=>'Show duration','value'=>$duration($this->show_duration),'hint'=>null],['label'=>'Average order rating','value'=>$this->avg_order_rating===null?null:number_format((float)$this->avg_order_rating,2),'hint'=>'Whatnot reports none until a show has been rated']]];
    }

    public function itemReportIsFiled(): bool { $entry=$this->relationLoaded('streamerLogEntry')?$this->streamerLogEntry:$this->streamerLogEntry()->first();return(bool)$entry?->submitted_at; }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class,'created_by'); }
    public function shippingSurcharges(): HasMany { return $this->hasMany(ShippingSurcharge::class); }
    public const ANALYTICS_COLUMNS=['gross_revenue','completed_earnings','buyers_count','total_views'];
    public function scopeMissingAnalytics(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder { return $query->where(function($q){foreach(self::ANALYTICS_COLUMNS as $column)$q->orWhereNull($column);}); }
    public function scopeMissingShipments(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder { return $query->whereNull('last_shipments_synced_at')->whereDoesntHave('shipments'); }
    public function shipments(): HasMany { return $this->hasMany(Shipment::class); }
    public function streamerLogEntry(): HasOne { return $this->hasOne(StreamerLogEntry::class); }
    public function changeLogs(): HasMany { return $this->hasMany(ShowChangeLog::class)->latest(); }
    public function reopeningRequests(): HasMany { return $this->hasMany(ShowReopeningRequest::class); }
    public function pendingReopeningRequest(): HasOne { return $this->hasOne(ShowReopeningRequest::class)->where('status','pending'); }

    public function trackChanges(array $newValues,string $source='manual'): self
    {
        $fieldsToTrack=['gross_revenue','whatnot_net','whatnot_fees','tips','status','show_date','units_sold'];foreach($newValues as $field=>$newValue){if(!in_array($field,$fieldsToTrack))continue;$oldValue=$this->{$field};if((string)$oldValue===(string)$newValue)continue;ShowChangeLog::create(['show_id'=>$this->id,'field_name'=>$field,'old_value'=>is_scalar($oldValue)?(string)$oldValue:json_encode($oldValue),'new_value'=>is_scalar($newValue)?(string)$newValue:json_encode($newValue),'changed_by'=>auth()->user()?->email??'system','source'=>$source]);}return $this;
    }

    public function detectStreamers(): array
    {
        $title=strtolower($this->title??'');if(empty($title))return[];$suggestions=[];$matchesWholeWord=fn(string $needle,string $haystack):bool=>$needle!==''&&preg_match('/\b'.preg_quote($needle,'/').'\b/u',$haystack)===1;
        foreach(Streamer::where('status','active')->get(['id','name']) as $streamer){$lowerName=strtolower($streamer->name);if($matchesWholeWord($lowerName,$title)){$suggestions[]=['streamer_id'=>$streamer->id,'streamer_name'=>$streamer->name,'confidence'=>'high','reason'=>"Name \"{$streamer->name}\" found in show title"];continue;}foreach(explode(' ',$lowerName) as $part){if(strlen($part)>=4&&$matchesWholeWord($part,$title)){$suggestions[]=['streamer_id'=>$streamer->id,'streamer_name'=>$streamer->name,'confidence'=>'medium','reason'=>"Name part \"{$part}\" found in show title"];break;}}}
        if(!empty($suggestions)){$this->updateQuietly(['ai_streamer_suggestion'=>$suggestions]);if($this->streamers()->count()===0){$first=true;foreach($suggestions as $s){if($s['confidence']==='high'){$this->streamers()->attach($s['streamer_id'],['is_primary'=>$first]);$first=false;}}}}return$suggestions;
    }

    public function defaultInventoryLocation(): ?InventoryLocation
    {
        $streamer=$this->relationLoaded('streamers')?$this->streamers->first():$this->streamers()->first();if(!$streamer)return null;return InventoryLocation::where('streamer_id',$streamer->id)->where('status','active')->orderByRaw("CASE type WHEN 'streamer_inventory' THEN 0 ELSE 1 END")->first();
    }

    public function pipelineSteps(): array
    {
        $dr=$this->relationLoaded('latestDeductionRequest')?$this->getRelation('latestDeductionRequest'):$this->latestDeductionRequest()->first();$log=$this->relationLoaded('streamerLogEntry')?$this->getRelation('streamerLogEntry'):$this->streamerLogEntry()->first();$hasPayouts=$this->relationLoaded('payouts')?$this->payouts->isNotEmpty():$this->payouts()->exists();$mappingDone=in_array($this->status,['pending_approval','reconciled','closed']);$mappingCurrent=in_array($this->status,['pending_review','mapping']);$approvalDone=in_array($this->status,['reconciled','closed']);$approvalCurrent=$this->status==='pending_approval';$streamerDone=$log&&in_array($log->status,['streamer_reviewed','admin_approved']);$streamerCurrent=$log&&$log->status==='pending';$logApprovedDone=$log&&$log->status==='admin_approved';$logApprovedCurrent=$log&&$log->status==='streamer_reviewed';$primaryStreamer=$this->relationLoaded('streamers')?$this->streamers->first():$this->primaryStreamer();$needsFulfillmentStep=$primaryStreamer?->payout_type==='pwe_labels';$fulfillmentDone=$log&&$log->fulfillment_reviewed_at!==null;$fulfillmentCurrent=$log&&$log->status==='admin_approved'&&!$fulfillmentDone;$lineCount=fn()=>$dr?$dr->lines()->count():null;
        $steps=[['key'=>'created','label'=>'Show Created','status'=>'done','note'=>$this->created_at?->format('M j, Y')],['key'=>'mapped','label'=>'Items Mapped','status'=>$mappingDone?'done':($mappingCurrent?'current':'pending'),'note'=>$lineCount()!==null?"{$lineCount()} line".($lineCount()===1?'':'s'):null],['key'=>'deduction_approved','label'=>'Deduction Approved','status'=>$approvalDone?'done':($approvalCurrent?'current':'pending'),'note'=>$dr?(DeductionRequest::statusLabels()[$dr->status]??null):null],['key'=>'streamer_reviewed','label'=>'Streamer Reviewed','status'=>$streamerDone?'done':($streamerCurrent?'current':'pending'),'note'=>$log?->streamer_reviewed_at?->format('M j, Y')],['key'=>'log_approved','label'=>'Log Approved','status'=>$logApprovedDone?'done':($logApprovedCurrent?'current':'pending'),'note'=>$log?->reviewed_at?->format('M j, Y')]];
        if($needsFulfillmentStep)$steps[]=['key'=>'fulfillment_reviewed','label'=>'Fulfillment Reviewed','status'=>$fulfillmentDone?'done':($fulfillmentCurrent?'current':'pending'),'note'=>$log?->fulfillment_reviewed_at?->format('M j, Y')];$steps[]=['key'=>'payout','label'=>'Payout Calculated','status'=>$hasPayouts?'done':'pending','note'=>null];if($this->status==='cancelled'){foreach($steps as &$step){if($step['status']==='pending')$step['status']='skipped';}}return$steps;
    }

    protected static array $priorRevenuesCache = [];

    public function isRevenueOutlier(): bool
    {
        if ($this->gross_revenue === null) return false;
        $streamer = $this->relationLoaded('streamers') ? $this->streamers->first() : $this->primaryStreamer();
        if (! $streamer) return false;
        $pool = static::$priorRevenuesCache[$streamer->id] ??= static::whereHas('streamers', fn ($q) => $q->where('streamers.id', $streamer->id))->whereNotIn('status', ['cancelled', 'draft'])->whereNotNull('gross_revenue')->orderByDesc('show_date')->limit(20)->pluck('gross_revenue', 'id')->map(fn ($v) => (float) $v);
        $priorRevenues = $pool->except([$this->id])->take(10);
        if ($priorRevenues->count() < 5) return false;
        $mean = $priorRevenues->avg(); if ($mean <= 0) return false;
        $ratio = (float) $this->gross_revenue / $mean;
        return $ratio > 2.5 || $ratio < 0.35;
    }

    public static function weekPacing(): array
    {
        $today=now();$daysIntoWeek=$today->dayOfWeekIso;$weekStart=$today->copy()->startOfWeek();
        $revenueThrough=fn(\Illuminate\Support\Carbon $start,\Illuminate\Support\Carbon $end):float=>(float)static::whereBetween('show_date',[$start->toDateString(),$end->copy()->endOfDay()->toDateTimeString()])->whereNotIn('status',['cancelled'])->inChannelContext()->sum('gross_revenue');
        $thisWeekRevenue=$revenueThrough($weekStart,$today);$baselineRevenues=[];for($i=1;$i<=4;$i++){$priorStart=$weekStart->copy()->subWeeks($i);$priorEnd=$priorStart->copy()->addDays($daysIntoWeek-1);$baselineRevenues[]=$revenueThrough($priorStart,$priorEnd);} $baselineAvg=array_sum($baselineRevenues)/count($baselineRevenues);$pacingPct=$baselineAvg>0?round((($thisWeekRevenue-$baselineAvg)/$baselineAvg)*100,1):null;
        return ['this_week_revenue'=>round($thisWeekRevenue,2),'baseline_avg'=>round($baselineAvg,2),'pacing_pct'=>$pacingPct,'days_into_week'=>$daysIntoWeek];
    }

    public static function monthPacing(): array
    {
        $today=now();$daysIntoMonth=$today->day;$monthStart=$today->copy()->startOfMonth();
        $revenueThrough=fn(\Illuminate\Support\Carbon $start,\Illuminate\Support\Carbon $end):float=>(float)static::whereBetween('show_date',[$start->toDateString(),$end->copy()->endOfDay()->toDateTimeString()])->whereNotIn('status',['cancelled'])->inChannelContext()->sum('gross_revenue');
        $thisMonthRevenue=$revenueThrough($monthStart,$today);$baselineRevenues=[];for($i=1;$i<=3;$i++){$priorStart=$monthStart->copy()->subMonths($i);$priorEnd=$priorStart->copy()->addDays(min($daysIntoMonth,$priorStart->daysInMonth)-1);$baselineRevenues[]=$revenueThrough($priorStart,$priorEnd);} $baselineAvg=array_sum($baselineRevenues)/count($baselineRevenues);$pacingPct=$baselineAvg>0?round((($thisMonthRevenue-$baselineAvg)/$baselineAvg)*100,1):null;$projectedMonthTotal=$daysIntoMonth>0?round(($thisMonthRevenue/$daysIntoMonth)*$today->daysInMonth,2):null;
        return ['this_month_revenue'=>round($thisMonthRevenue,2),'baseline_avg'=>round($baselineAvg,2),'pacing_pct'=>$pacingPct,'days_into_month'=>$daysIntoMonth,'projected_month_total'=>$projectedMonthTotal];
    }

    public static function formatLabels(): array { return ['standard'=>'Standard break','sudden_death'=>'Sudden death','big_giveaway'=>'Big giveaway','low_giveaway'=>'Low giveaway','themed'=>'Themed / special','personal'=>'Personal break']; }
    public function formatLabel(): string { return static::formatLabels()[$this->show_format] ?? 'Unclassified'; }
    public static function statusLabels(): array { return ['draft'=>'Draft','pending_review'=>'Pending Review','mapping'=>'Mapping','pending_approval'=>'Pending Approval','reconciled'=>'Reconciled','closed'=>'Closed','cancelled'=>'Cancelled']; }
    public static function importSourceLabels(): array { return ['manual'=>'Manual','auto_whatnot'=>'Auto (Whatnot)']; }
}
