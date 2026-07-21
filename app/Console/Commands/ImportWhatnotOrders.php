<?php

namespace App\Console\Commands;

use App\Models\Show;
use App\Services\WhatnotScraper;
use Illuminate\Console\Command;

class ImportWhatnotOrders extends Command
{
    protected $signature = 'whatnot:import-orders
                            {--show=    : Show ID to import orders for (omit for all shows with a detail_url)}
                            {--recent   : Only shows from the last 30 days}
                            {--new-only : Only shows that have a detail_url but no orders imported yet}
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

        $shows = $query->orderByDesc('show_date')->get();

        if ($shows->isEmpty()) {
            $this->warn('No shows found with a detail_url. Run `php artisan whatnot:import` first to populate show URLs.');
            return self::SUCCESS;
        }

        $totalCreated = 0;
        $totalSkipped = 0;

        foreach ($shows as $show) {
            $this->line("Importing orders for: {$show->title} ({$show->show_date?->format('Y-m-d')})");

            try {
                $result = $scraper->importShowOrders(
                    $show,
                    $debug,
                    onProgress: fn (string $line) => $this->line("  <fg=gray>{$line}</>"),
                );
                $this->info("  ✓ {$result['created']} created, {$result['skipped']} skipped");
                $totalCreated += $result['created'];
                $totalSkipped += $result['skipped'];

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
