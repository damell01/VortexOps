<?php

namespace App\Console\Commands;

use App\Models\WhatnotChannel;
use App\Services\WhatnotScraper;
use Illuminate\Console\Command;

class ImportWhatnotShows extends Command
{
    protected $signature = 'whatnot:import
                            {--channel= : WhatnotChannel name or ID — if omitted, imports all enabled channels}
                            {--limit=50 : Max number of shows to fetch per channel per run}
                            {--debug    : Save Playwright screenshots to /tmp for debugging selectors}';

    protected $description = 'Scrape completed shows from the Whatnot seller dashboard and import them';

    public function handle(WhatnotScraper $scraper): int
    {
        $limit = (int) $this->option('limit');
        $debug = (bool) $this->option('debug');

        if ($channelOpt = $this->option('channel')) {
            // Single-channel mode
            $channel = is_numeric($channelOpt)
                ? WhatnotChannel::find($channelOpt)
                : WhatnotChannel::where('name', $channelOpt)->orWhere('whatnot_username', $channelOpt)->first();

            if (! $channel) {
                $this->error("Channel not found: {$channelOpt}");
                return self::FAILURE;
            }

            $this->info("Importing channel: {$channel->name} (@{$channel->whatnot_username})…");

            try {
                $result = $scraper->importShows(channel: $channel, limit: $limit, debug: $debug);
            } catch (\RuntimeException $e) {
                $this->error($e->getMessage());
                $this->printTroubleshootingHints($e->getMessage());
                return self::FAILURE;
            }

            $this->info("Import complete:");
            $this->table(['Created', 'Updated', 'Skipped'], [[$result['created'], $result['updated'], $result['skipped']]]);

        } else {
            // All-enabled-channels mode (default)
            $channels = WhatnotChannel::where('include_in_import', true)->where('status', 'active')->get();

            if ($channels->isEmpty()) {
                $this->warn('No active channels with "Include in Import" enabled.');
                $this->line('Enable channels in Settings → Whatnot Channels, or run with --channel=NAME for a specific one.');
                return self::FAILURE;
            }

            $this->info("Importing {$channels->count()} channel(s): " . $channels->pluck('name')->join(', ') . '…');

            $totals = ['created' => 0, 'updated' => 0, 'skipped' => 0];
            $rows   = [];

            foreach ($channels as $channel) {
                $this->line("  → {$channel->name} (@{$channel->whatnot_username})");
                try {
                    $result = $scraper->importShows(channel: $channel, limit: $limit, debug: $debug);
                    $totals['created'] += $result['created'];
                    $totals['updated'] += $result['updated'];
                    $totals['skipped'] += $result['skipped'];
                    $rows[] = [$channel->name, $result['created'], $result['updated'], $result['skipped']];
                } catch (\RuntimeException $e) {
                    $this->error("  Channel \"{$channel->name}\" failed: " . $e->getMessage());
                    $this->printTroubleshootingHints($e->getMessage());
                    $rows[] = [$channel->name, 'ERROR', 'ERROR', $e->getMessage()];
                }
            }

            $this->newLine();
            $this->info('Import complete:');
            $this->table(['Channel', 'Created', 'Updated', 'Skipped'], $rows);
            $this->line("Total: {$totals['created']} created, {$totals['updated']} updated, {$totals['skipped']} skipped");
        }

        return self::SUCCESS;
    }

    private function printTroubleshootingHints(string $message): void
    {
        if (str_contains($message, 'WHATNOT_EMAIL')) {
            $this->line('');
            $this->line('Add the following to your .env:');
            $this->line('  WHATNOT_EMAIL=your@email.com');
            $this->line('  WHATNOT_PASSWORD=yourpassword');
        }

        if (str_contains($message, 'selectors')) {
            $this->line('');
            $this->line('Re-run with --debug to capture screenshots:');
            $this->line('  php artisan whatnot:import --debug');
            $this->line('Screenshots saved to /tmp/whatnot-debug-*.png');
        }
    }
}
