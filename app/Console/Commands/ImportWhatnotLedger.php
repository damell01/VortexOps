<?php

namespace App\Console\Commands;

use App\Models\WhatnotChannel;
use App\Services\WhatnotScraper;
use Illuminate\Console\Command;

class ImportWhatnotLedger extends Command
{
    protected $signature = 'whatnot:import-ledger
                            {--channel=  : Channel name or ID — omit to import all enabled channels}
                            {--from=     : Start date YYYY-MM-DD (default: 7 days ago)}
                            {--to=       : End date YYYY-MM-DD (default: today)}
                            {--days=     : Convenience: last N days ending today (overrides --from)}
                            {--debug     : Save Playwright screenshots to /tmp}';

    protected $description = 'Scrape the Whatnot financial ledger into whatnot_ledger_entries (windowed by <=31 days)';

    public function handle(WhatnotScraper $scraper): int
    {
        $debug = (bool) $this->option('debug');
        $to    = $this->option('to') ?: now()->toDateString();

        if ($days = $this->option('days')) {
            $from = now()->subDays((int) $days)->toDateString();
        } else {
            $from = $this->option('from') ?: now()->subDays(7)->toDateString();
        }

        $channels = $this->resolveChannels();
        if ($channels->isEmpty()) {
            $this->warn('No channels to import. Enable channels in Settings → Whatnot Channels, or pass --channel=NAME.');
            return self::FAILURE;
        }

        $this->info("Importing Whatnot ledger {$from} → {$to} for {$channels->count()} channel(s)…");

        $rows = [];
        foreach ($channels as $channel) {
            $this->line("  → {$channel->name} (@{$channel->whatnot_username})");
            try {
                $res = $scraper->importLedger($channel, $from, $to, $debug);
                $rows[] = [$channel->name, $res['created'], $res['skipped'], $res['windows']];
            } catch (\RuntimeException $e) {
                $this->error("    {$e->getMessage()}");
                $rows[] = [$channel->name, 'ERROR', 'ERROR', $e->getMessage()];
            }
        }

        $this->newLine();
        $this->table(['Channel', 'Created', 'Skipped', 'Windows'], $rows);

        return self::SUCCESS;
    }

    /** @return \Illuminate\Support\Collection<int,WhatnotChannel> */
    private function resolveChannels()
    {
        if ($opt = $this->option('channel')) {
            $channel = is_numeric($opt)
                ? WhatnotChannel::find($opt)
                : WhatnotChannel::where('name', $opt)->orWhere('whatnot_username', $opt)->first();

            return collect($channel ? [$channel] : []);
        }

        return WhatnotChannel::where('include_in_import', true)->where('status', 'active')->get();
    }
}
