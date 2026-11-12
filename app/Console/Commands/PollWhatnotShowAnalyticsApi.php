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
            ->missingAnalytics()
            ->whereNotNull('whatnot_show_id')
            ->whereNotNull('whatnot_channel_id')
            ->whereDate('show_date', '>=', BusinessTime::local()->subDays(3)->toDateString())
            ->orderByDesc('show_date')
            ->limit(min(500, $limit * 8))
            ->get()
            ->filter(function (Show $show): bool {
                $attempt = data_get($show->raw_import_payload, '_analytics_last_attempt_at');
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
