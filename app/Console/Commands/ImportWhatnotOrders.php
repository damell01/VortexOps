<?php

namespace App\Console\Commands;

use App\Models\Show;
use App\Services\WhatnotScraper;
use Illuminate\Console\Command;

class ImportWhatnotOrders extends Command
{
    protected $signature = 'whatnot:import-orders
                            {--show=  : Show ID to import orders for (omit for all shows with a detail_url)}
                            {--recent : Only shows from the last 30 days}
                            {--debug  : Save Playwright screenshots to /tmp for debugging selectors}';

    protected $description = 'Scrape order/lot data for completed Whatnot shows and store buyer + item details';

    public function handle(WhatnotScraper $scraper): int
    {
        $debug = (bool) $this->option('debug');

        $query = Show::whereNotNull('detail_url');

        if ($showId = $this->option('show')) {
            $query->where('id', $showId);
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
                $result = $scraper->importShowOrders($show, $debug);
                $this->info("  ✓ {$result['created']} created, {$result['skipped']} skipped");
                $totalCreated += $result['created'];
                $totalSkipped += $result['skipped'];
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
}
