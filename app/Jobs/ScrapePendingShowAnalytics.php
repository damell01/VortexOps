<?php

namespace App\Jobs;

use App\Models\Show;
use App\Models\WhatnotChannel;
use App\Services\WhatnotReportingReconciler;
use App\Services\WhatnotApiOrderApplier;
use App\Jobs\RetryShowAnalytics;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Analytics-only Seller Hub fallback for an API-pending show.
 * Uses the existing targeted analytics reconciler; never imports orders or shipments.
 */
class ScrapePendingShowAnalytics implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 7200;
    public int $uniqueFor = 1800;

    public function __construct(public int $showId)
    {
        $this->onConnection('redis_analytics');
        $this->onQueue('whatnot-analytics-browser');
    }

    public function uniqueId(): string
    {
        return (string) $this->showId;
    }

    public function handle(WhatnotReportingReconciler $reconciler): void
    {
        if (! WhatnotApiOrderApplier::writesAllowed()) return;

        $show = Show::query()->readyForAnalytics()->find($this->showId);
        if (! $show || ! $show->whatnot_show_id || ! $show->whatnot_channel_id) return;
        if ($show->analytics_sync_status === 'unavailable' && $show->analytics_unavailable_at) return;
        $initialMeta = is_array($show->raw_import_payload) ? $show->raw_import_payload : [];
        if (! empty($initialMeta['_analytics_api_generated_at'])) return;

        $channel = WhatnotChannel::find($show->whatnot_channel_id);
        if (! $channel || ! $channel->whatnot_username) return;

        // Persist a separate browser cooldown; API retries must remain independent.
        $meta = is_array($show->raw_import_payload) ? $show->raw_import_payload : [];
        $last = $meta['_analytics_browser_last_attempt_at'] ?? null;
        if ($last) {
            try {
                if (Carbon::parse($last)->gt(now()->subMinutes(30))) return;
            } catch (\Throwable) {
                // A malformed legacy timestamp must not permanently block recovery.
            }
        }

        $meta['_analytics_browser_last_attempt_at'] = now()->toIso8601String();
        $show->forceFill(['raw_import_payload' => $meta])->saveQuietly();

        Log::info('Starting analytics-only Seller Hub fallback', ['show_id' => $show->id]);
        $result = $reconciler->backfillAnalytics(
            $channel,
            Carbon::parse($show->show_date)->startOfDay(),
            1,
            null,
            [$show->id],
        );
        $show->refresh();
        $meta = is_array($show->raw_import_payload) ? $show->raw_import_payload : [];
        if (($result['updated'] ?? 0) > 0 && ($result['failed'] ?? 0) === 0) {
            $meta['_analytics_browser_finished_at'] = now()->toIso8601String();
            $show->forceFill(['raw_import_payload' => $meta])->saveQuietly();
        }
        // The API poll scheduler handles follow-up checks with its own cooldown.
        // Do not dispatch an immediate duplicate API request here.
        Log::info('Finished analytics-only Seller Hub fallback', [
            'show_id' => $show->id,
            'result' => $result,
        ]);
    }
}
