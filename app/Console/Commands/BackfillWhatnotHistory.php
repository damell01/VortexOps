<?php

namespace App\Console\Commands;

use App\Models\Show;
use App\Models\WhatnotChannel;
use Illuminate\Console\Command;

/**
 * Walk the back catalogue until every past show has its analytics and its
 * shipments.
 *
 * The scraper itself intentionally works in small batches so one Whatnot page
 * failure does not turn into hundreds of bad writes. This command is the
 * deliberate full-history runner: by default it keeps taking batches until
 * there is nothing left to fill, or until a pass makes no progress.
 */
class BackfillWhatnotHistory extends Command
{
    /** whatnot:refresh-recent clamps its own limit to this. */
    private const BATCH_SIZE = 20;

    protected $signature = 'whatnot:backfill-history
                            {--batches=0 : Maximum batches to run; 0 means keep going until all outstanding history is filled}
                            {--sleep=20 : Seconds to wait between batches}
                            {--days=90 : Rolling refresh window; missing data is repaired regardless of age}
                            {--dry-run : Report what is outstanding and stop}
                            {--verify : Take one show all the way through and report what actually landed}';

    protected $description = 'Backfill analytics and shipments for all past Whatnot shows, in paced batches';

    public function handle(): int
    {
        $channel = RefreshRecentWhatnotShows::targetChannel();

        if (! $channel) {
            $this->error('No Whatnot channel exists in VortexOps.');

            return self::FAILURE;
        }

        $outstanding = $this->outstanding($channel);

        $this->newLine();
        $this->line(sprintf(
            '  <fg=gray>%d channel(s) —</> %d <fg=gray>past shows,</> <fg=yellow>%d</> <fg=gray>still missing analytics or shipments.</>',
            count(WhatnotChannel::importedIds()),
            $this->pastShows($channel)->count(),
            $outstanding,
        ));

        if ($outstanding > 0) {
            $this->line(sprintf(
                '  <fg=gray>%d missing figures · %d missing shipments</>',
                (clone $this->pastShows($channel))->missingAnalytics()->count(),
                (clone $this->pastShows($channel))->missingShipments()->count(),
            ));
        }

        $this->reportExcludedChannels();

        if ($outstanding === 0) {
            $this->newLine();
            $this->info('Nothing outstanding — every past show has both.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->newLine();
            $passes = (int) ceil($outstanding / self::BATCH_SIZE);
            $this->line('  <fg=gray>' . $passes . ' ' . str('pass')->plural($passes) . ' of up to ' . self::BATCH_SIZE . '.</>');
            $this->comment('Dry run. Re-run without --dry-run to work through all outstanding history.');

            return self::SUCCESS;
        }

        if ($this->option('verify')) {
            return $this->verify($channel);
        }

        // 0 means "all". We still scrape at most 20 shows per browser pass,
        // but there is no overall 8/20/400-show ceiling anymore. The loop ends
        // only when every outstanding past show is filled, a pass makes no
        // progress, or the scraper reports a real failure/rate limit.
        $batchLimit = max(0, (int) $this->option('batches'));
        $sleep      = max(0, (int) $this->option('sleep'));
        $days       = (int) $this->option('days');
        $batch      = 0;

        while ($batchLimit === 0 || $batch < $batchLimit) {
            $batch++;
            $before = $this->outstanding($channel);

            if ($before === 0) {
                $this->newLine();
                $this->info('Everything is in. Stopping.');

                return self::SUCCESS;
            }

            $totalLabel = $batchLimit === 0 ? 'all' : (string) $batchLimit;
            $this->newLine();
            $this->line("  <fg=cyan>Batch {$batch} of {$totalLabel}</> — {$before} outstanding");

            // RefreshRecentWhatnotShows owns the actual browser pass. Keep this
            // runner thin: it repeatedly asks that command for the next due batch.
            // The rolling --days window controls stale rechecks; rows with missing
            // analytics/shipments are selected regardless of age by dueShows().
            $exit = $this->call('whatnot:refresh-recent', [
                '--limit' => self::BATCH_SIZE,
                '--days'  => $days,
            ]);

            if ($exit === RefreshRecentWhatnotShows::SKIPPED_LOCKED) {
                $this->newLine();
                $this->error('Nothing was scraped: the browser lock is held, so no batch can start.');
                $this->line('  <fg=gray>Another Whatnot job is running, or a previous one was killed before it could</>');
                $this->line('  <fg=gray>release the lock. Check with php artisan whatnot:unlock, then run this again.</>');

                return self::FAILURE;
            }

            if ($exit !== self::SUCCESS) {
                $this->newLine();
                $this->error('The scraper stopped. Nothing further was attempted.');
                $this->explainExit($exit);

                return self::FAILURE;
            }

            $after = $this->outstanding($channel);

            if ($after >= $before) {
                $this->newLine();
                $this->warn("Nothing moved on that pass — {$after} shows are still outstanding and are not being filled.");
                $this->line('  <fg=gray>If another browser job held the lock, try again shortly. Otherwise run</>');
                $this->line('  <fg=gray>php artisan whatnot:analytics-coverage to see which fields are missing.</>');

                return self::SUCCESS;
            }

            $filled = $before - $after;
            $this->line("  <fg=green>{$filled} show(s) filled this pass.</> <fg=gray>{$after} still outstanding.</>");

            if ($sleep > 0 && ($batchLimit === 0 || $batch < $batchLimit)) {
                sleep($sleep);
            }
        }

        $remaining = $this->outstanding($channel);

        $this->newLine();

        if ($remaining === 0) {
            $this->info('Done. Every past show has its analytics and its shipments.');

            return self::SUCCESS;
        }

        $this->line("  <fg=green>Done.</> <fg=gray>{$remaining} still outstanding — run it again to continue.</>");

        return self::SUCCESS;
    }

    private function reportExcludedChannels(): void
    {
        $excluded = WhatnotChannel::query()
            ->whereNotIn('id', WhatnotChannel::importedIds())
            ->get()
            ->map(fn (WhatnotChannel $channel) => [
                $channel->name,
                Show::query()
                    ->where('whatnot_channel_id', $channel->id)
                    ->whereDate('show_date', '<=', today())
                    ->count(),
            ])
            ->filter(fn (array $row) => $row[1] > 0);

        if ($excluded->isEmpty()) {
            return;
        }

        $this->newLine();
        $this->warn('Not included in imports, so nothing fetches their shows:');

        foreach ($excluded as [$name, $count]) {
            $this->line("  <fg=gray>{$name}: {$count} past shows</>");
        }

        $this->line('  <fg=gray>Turn on "include in import" for a channel to have it kept up to date.</>');
    }

    private function verify(WhatnotChannel $channel): int
    {
        $show = $this->pastShows($channel)
            ->where(fn ($query) => $query
                ->missingAnalytics()
                ->orWhere(fn ($q) => $q->missingShipments()))
            ->orderByDesc('show_date')
            ->first();

        if (! $show) {
            $this->info('Nothing outstanding to verify against.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->line('  <fg=cyan>Verifying on one show:</> ' . ($show->title ?: $show->whatnot_show_id));
        $this->line('  <fg=gray>' . $show->show_date?->toFormattedDateString() . ' · ' . $show->whatnot_show_id . '</>');
        $this->newLine();

        $shipmentsBefore = $show->shipments()->count();

        $exit = $this->call('whatnot:refresh-recent', [
            '--limit' => 1,
            '--days'  => (int) $this->option('days'),
            '--debug' => true,
        ]);

        $show->refresh();

        $this->newLine();

        if ($exit === RefreshRecentWhatnotShows::SKIPPED_LOCKED) {
            $this->error('Nothing ran: the browser lock is held, so nothing was verified.');
            $this->line('  <fg=gray>Run php artisan whatnot:unlock, then try this again.</>');

            return self::FAILURE;
        }

        if ($exit !== self::SUCCESS) {
            $this->error('The scraper did not complete. Nothing was verified.');
            $this->explainExit($exit);

            return self::FAILURE;
        }

        $analytics = $show->last_analytics_synced_at !== null;
        $shipments = $show->last_shipments_synced_at !== null;
        $gained    = $show->shipments()->count() - $shipmentsBefore;

        $mark = fn (bool $ok) => $ok ? '<fg=green>yes</>' : '<fg=red>no</>';

        $this->table(['What', 'Arrived', 'Detail'], [
            ['Analytics', $mark($analytics), $analytics
                ? 'gross ' . ($show->gross_revenue ?? '—') . ', buyers ' . ($show->buyers_count ?? '—')
                : 'no metrics were written'],
            ['Shipments', $mark($shipments), $shipments
                ? $show->shipments()->count() . ' rows on this show (' . ($gained >= 0 ? '+' : '') . $gained . ' this run)'
                : 'no shipment sync was recorded'],
        ]);

        if ($analytics && $shipments) {
            $this->info('Signed in and scraping. Safe to run the full backfill.');

            return self::SUCCESS;
        }

        $this->warn('The scraper ran but did not fill everything on this show.');
        $this->line('  <fg=gray>A show can legitimately have no shipments. If both are missing on several</>');
        $this->line('  <fg=gray>shows, the page markup has moved — check the enrich lines in the output above.</>');

        return self::SUCCESS;
    }

    private function explainExit(int $exit): void
    {
        match ($exit) {
            3 => $this->explain('The Whatnot session is not being accepted.', [
                'Cloudflare served a challenge instead of the dashboard. Check the session with',
                'php artisan whatnot:login --test, and re-import cookies with php artisan whatnot:login.',
                'If the session is good, this server\'s address is being judged: set WHATNOT_PROXY to a',
                'residential SOCKS proxy, because no browser tuning changes an IP.',
            ]),
            4 => $this->explain('Rate limited.', [
                'Leave it an hour. Running it more often makes it worse.',
            ]),
            2 => $this->explain('The Seller Hub markup has moved.', [
                'The scraper reached Whatnot but could not find what it expected. Re-run with --debug',
                'and update the selectors in scripts/whatnot-production-sync.cjs.',
            ]),
            default => $this->explain("The scraper exited with code {$exit}.", [
                'Re-run with --debug for the stage-by-stage output.',
            ]),
        };
    }

    /** @param  list<string>  $lines */
    private function explain(string $headline, array $lines): void
    {
        $this->line('  <fg=yellow>' . $headline . '</>');

        foreach ($lines as $line) {
            $this->line('  <fg=gray>' . $line . '</>');
        }
    }

    private function outstanding(WhatnotChannel $channel): int
    {
        return $this->pastShows($channel)
            ->where(fn ($query) => $query
                ->missingAnalytics()
                ->orWhere(fn ($q) => $q->missingShipments()))
            ->count();
    }

    private function pastShows(WhatnotChannel $channel)
    {
        return Show::query()
            ->whereIn('whatnot_channel_id', WhatnotChannel::importedIds())
            ->whereNotNull('whatnot_show_id')
            ->whereDate('show_date', '<=', today());
    }
}
