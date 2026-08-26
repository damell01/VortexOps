<?php

namespace App\Console\Commands;

use App\Models\Show;
use App\Services\WhatnotScraper;
use Illuminate\Console\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;

class ImportWhatnotOrders extends Command
{
    protected $signature = 'whatnot:import-orders
                            {--show=    : Show ID to import orders for (omit for all shows with a detail_url)}
                            {--recent   : Only shows from the last 30 days}
                            {--new-only : Only shows that have a detail_url but no orders imported yet}
                            {--limit=   : Stop after this many shows (the scheduled backfill is bounded; a manual run is not)}
                            {--debug    : Save Playwright screenshots to /tmp for debugging selectors}';

    protected $description = 'Scrape order/lot data for completed Whatnot shows and store buyer + item details';

    public function handle(WhatnotScraper $scraper): int
    {
        $debug = (bool) $this->option('debug');

        // Future-dated (scheduled) shows can't have orders yet — and since this
        // walks newest-first, they'd be scraped before any real show. Skip them.
        $query = Show::whereNotNull('detail_url')
            ->where('show_date', '<=', now()->endOfDay());

        if ($showId = $this->option('show')) {
            $query->where('id', $showId);
        }

        if ($this->option('new-only')) {
            $query->whereDoesntHave('orders');
        }

        if ($this->option('recent')) {
            $query->where('show_date', '>=', now()->subDays(30)->toDateString());
        }

        // An unbounded backfill takes the browser lock for every show it finds,
        // one after another, for as long as that takes — hours, on a few hundred
        // shows. Every other Whatnot job queues behind it, and because this one
        // re-acquires the lock immediately on the next iteration, they lose the
        // race practically every time. That is how whatnot:refresh-recent went
        // months without a turn and half the catalogue ended up with no
        // analytics. A scheduled run is bounded; a manual one still is not.
        $outstanding = (clone $query)->count();

        if ($limit = (int) $this->option('limit')) {
            $query->limit($limit);
        }

        $shows = $query->orderByDesc('show_date')->get();

        if ($shows->isEmpty()) {
            $this->warn('No shows found with a detail_url. Run `php artisan whatnot:import` first to populate show URLs.');
            return self::SUCCESS;
        }

        if ($limit && $outstanding > $shows->count()) {
            $this->line(sprintf(
                '  <fg=gray>%d of %d outstanding shows this run; the rest wait for the next one.</>',
                $shows->count(),
                $outstanding,
            ));
        }

        $totalCreated = 0;
        $totalSkipped = 0;

        foreach ($shows as $show) {
            $this->line("Importing orders for: " . OutputFormatter::escape((string) $show->title) . " ({$show->show_date?->format('Y-m-d')})");

            try {
                $result = $scraper->importShowOrders(
                    $show,
                    $debug,
                    onProgress: fn (string $line) => $this->line("  <fg=gray>" . OutputFormatter::escape($line) . "</>"),
                );
                $this->info("  ✓ {$result['created']} created, {$result['skipped']} skipped");
                $totalCreated += $result['created'];
                $totalSkipped += $result['skipped'];

                // Keep order freshness separate from show-index / analytics /
                // shipment freshness. Direct assignment avoids mass-assignment
                // coupling and works as soon as the migration is applied.
                $show->setAttribute('last_orders_synced_at', now());
                $show->last_synced_at = now();
                $show->saveQuietly();

                if ($result['created'] > 0) {
                    $this->advanceShowStatus($show);
                }
            } catch (\RuntimeException $e) {
                $this->error("  ✗ {$e->getMessage()}");

                if (str_contains($e->getMessage(), 'selectors')) {
                    $this->line('    Re-run with --debug to capture screenshots and update SELECTORS in scripts/whatnot-scraper.cjs');
                }
            }
        }

        $this->info('');
        $this->info("Done — {$totalCreated} orders created, {$totalSkipped} skipped across {$shows->count()} shows.");

        return self::SUCCESS;
    }

    private function advanceShowStatus(Show $show): void
    {
        $show->refresh();

        // Only advance shows still in draft — higher statuses mean work is already underway
        if ($show->status !== 'draft') {
            return;
        }

        $show->update(['status' => 'pending_review']);
        $this->line("  → Transitioned to pending_review");
    }
}
