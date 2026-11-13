<?php

namespace App\Console\Commands;

use App\Jobs\ScrapePendingShowAnalytics;
use App\Models\Show;
use App\Support\BusinessTime;
use Carbon\Carbon;
use Illuminate\Console\Command;

class PollEndedShowScraperAnalytics extends Command
{
    protected $signature = 'whatnot:analytics-show-end-poll {--limit=2} {--dry-run}';
    protected $description = 'Queue Seller Hub analytics-only scraping for recently ended shows before API reconciliation';

    public function handle(): int
    {
        $limit = min(5, max(1, (int) $this->option('limit')));
        $shows = Show::query()
            ->readyForAnalytics()
            ->missingAnalytics()
            ->whereNotNull('whatnot_show_id')
            ->whereNotNull('whatnot_channel_id')
            ->whereDate('show_date', '>=', BusinessTime::local()->subDays(2)->toDateString())
            ->orderByDesc('show_date')
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->filter(function (Show $show): bool {
                $meta = is_array($show->raw_import_payload) ? $show->raw_import_payload : [];
                // The official API is authoritative; do not queue browser work
                // for shows that already have generated analytics.
                if (! empty($meta['_analytics_api_generated_at'])) return false;
                $attempt = $meta['_analytics_browser_last_attempt_at'] ?? null;
                if (! $attempt) return true;
                try {
                    return Carbon::parse($attempt)->lte(now()->subMinutes(30));
                } catch (\Throwable) {
                    return true;
                }
            })
            ->take($limit);

        foreach ($shows as $show) {
            if (! $this->option('dry-run')) ScrapePendingShowAnalytics::dispatch($show->id);
            $this->line(($this->option('dry-run') ? 'Would queue ' : 'Queued ').'Seller Hub analytics for show #'.$show->id);
        }
        $this->info($shows->count().' show(s) selected for Seller Hub analytics.');
        return self::SUCCESS;
    }
}
