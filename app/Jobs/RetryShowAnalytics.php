<?php
namespace App\Jobs;

use App\Models\Show;
use App\Models\WhatnotChannel;
use App\Services\WhatnotReportingReconciler;
use App\Support\WhatnotPipelineLock;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class RetryShowAnalytics implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public int $tries = 120;
    public int $timeout = 600;
    public int $uniqueFor = 7200;
    public function __construct(public int $showId) { $this->onQueue('whatnot'); }
    public function uniqueId(): string { return (string) $this->showId; }
    public function handle(WhatnotReportingReconciler $reconciler): void
    {
        $lock = WhatnotPipelineLock::acquire('Owner analytics retry for show #'.$this->showId);
        if (! $lock) { $this->release(30); return; }
        try {
            $show = Show::readyForAnalytics()->find($this->showId);
            $channel = $show ? WhatnotChannel::find($show->whatnot_channel_id) : null;
            if (! $show || ! $channel || ! $show->whatnot_show_id || ! $channel->include_in_import || $channel->status !== 'active') return;
            Cache::forget('whatnot-analytics-retry:'.$channel->id.':'.strtolower($show->whatnot_show_id));
            $reconciler->backfillAnalytics($channel, $show->show_date->copy()->startOfDay(), 1, null, [$show->id]);
        } finally { WhatnotPipelineLock::release($lock); }
    }
}
