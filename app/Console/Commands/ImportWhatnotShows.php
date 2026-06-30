<?php

namespace App\Console\Commands;

use App\Models\WhatnotChannel;
use App\Services\WhatnotScraper;
use Illuminate\Console\Command;

class ImportWhatnotShows extends Command
{
    protected $signature = 'whatnot:import
                            {--channel= : WhatnotChannel name or ID to tag imported shows against}
                            {--limit=50 : Max number of shows to fetch per run}
                            {--debug    : Save Playwright screenshots to /tmp for debugging selectors}';

    protected $description = 'Scrape completed shows from the Whatnot seller dashboard and import them';

    public function handle(WhatnotScraper $scraper): int
    {
        $this->info('Starting Whatnot show import…');

        $channel = null;
        if ($channelOpt = $this->option('channel')) {
            $channel = is_numeric($channelOpt)
                ? WhatnotChannel::find($channelOpt)
                : WhatnotChannel::where('name', $channelOpt)->first();

            if (! $channel) {
                $this->error("Channel not found: {$channelOpt}");
                return self::FAILURE;
            }

            $this->line("Tagging shows against channel: {$channel->name}");
        }

        try {
            $result = $scraper->importShows(
                channel: $channel,
                limit:   (int) $this->option('limit'),
                debug:   (bool) $this->option('debug'),
            );
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            if (str_contains($e->getMessage(), 'WHATNOT_EMAIL')) {
                $this->line('');
                $this->line('Add the following to your .env:');
                $this->line('  WHATNOT_EMAIL=your@email.com');
                $this->line('  WHATNOT_PASSWORD=yourpassword');
            }

            if (str_contains($e->getMessage(), 'selectors')) {
                $this->line('');
                $this->line('Re-run with --debug to capture screenshots:');
                $this->line('  php artisan whatnot:import --debug');
                $this->line('Screenshots saved to /tmp/whatnot-debug-*.png');
            }

            return self::FAILURE;
        }

        $this->info("Import complete:");
        $this->table(['Created', 'Updated', 'Skipped'], [
            [$result['created'], $result['updated'], $result['skipped']],
        ]);

        return self::SUCCESS;
    }
}
