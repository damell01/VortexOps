<?php

namespace App\Console\Commands;

use App\Jobs\RetryShowAnalytics;
use App\Models\Show;
use Illuminate\Console\Command;
use App\Support\BusinessTime;
use Carbon\Carbon;

/**
 * Poll recent completed shows using the Whatnot Owner API, not Seller Hub scraping.
 * Queue uniqueness prevents duplicate outstanding retries for a show.
 */
class PollWhatnotShowAnalyticsApi extends Command
{
    protected $signature = 'whatnot:analytics-api-poll {--limit=30} {--dry-run}';
    protected $description = 'Queue API analytics checks for recently completed shows without generated metrics';

    public function handle(): int
    {
        $limit = min(100, max(1, (int) $this->option('limit')));
        $shows = Show::query()
            ->readyForAnalytics()
            ->where(function ($query) {
                $query->missingAnalytics()
                    ->orWhere(function ($scraped) {
                        $scraped->whereNotNull('raw_import_payload->_analytics_browser_finished_at')
                            ->whereNull('raw_import_payload->_analytics_api_generated_at');
                    });
            })
            ->whereNull('raw_import_payload->_analytics_api_generated_at')
            ->whereNotNull('whatnot_show_id')
            ->whereNotNull('whatnot_channel_id')
            ->whereDate('show_date', '>=', BusinessTime::local()->subDays(3)->toDateString())
            ->orderByDesc('show_date')
            ->limit(min(500, $limit * 8))
            ->get()
            ->filter(function (Show $show): bool {
                $meta = is_array($show->raw_import_payload) ? $show->raw_import_payload : [];
                if (! empty($meta['_analytics_api_generated_at'])) return false;
                // Seller Hub is first for newly ended shows; API follows its first scrape.
                if (empty($meta['_analytics_browser_finished_at']) && empty($meta['_analytics_browser_last_attempt_at'])
                    && $show->show_date && Carbon::parse($show->show_date)->gte(now()->subDays(2))) return false;
                // A failed or hung browser scrape must never suppress API polling forever.
                if (! empty($meta['_analytics_browser_last_attempt_at']) && empty($meta['_analytics_browser_finished_at'])) {
                    try {
                        if (Carbon::parse($meta['_analytics_browser_last_attempt_at'])->gt(now()->subMinutes(5))) return false;
                    } catch (\Throwable) {}
                }
                $attempt = $meta['_analytics_last_attempt_at'] ?? null;
                if (! $attempt) return true;
                try {
                    return Carbon::parse($attempt)->lte(now()->subMinutes(15));
                } catch (\Throwable) {
                    return true;
                }
            })
            ->take($limit);

        foreach ($shows as $show) {
            if (! $this->option('dry-run')) {
                RetryShowAnalytics::dispatch($show->id);
            }
            $this->line(($this->option('dry-run') ? 'Would queue ' : 'Queued ').'show #'.$show->id);
        }
        $this->info($shows->count().' show(s) selected for API analytics checks.');
        return self::SUCCESS;
    }
}
