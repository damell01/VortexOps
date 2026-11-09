<?php

namespace App\Console\Commands;

use App\Models\WhatnotLedgerEntry;
use App\Services\LedgerAutoMatcher;
use Illuminate\Console\Command;

class MatchLedgerEntriesToShows extends Command
{
    protected $signature = 'ledger:match-shows {--limit=1000 : Maximum ledger entries to inspect}';
    protected $description = 'Link ledger transactions to shows using verified order evidence or explicit source livestream IDs';

    public function handle(LedgerAutoMatcher $matcher): int
    {
        $limit = min(100000, max(1, (int) $this->option('limit')));
        $stats = ['matched' => 0, 'categorized' => 0, 'unmatched' => 0, 'manual' => 0, 'flagged' => 0, 'failed' => 0];
        WhatnotLedgerEntry::query()->orderBy('id')->chunkById(500, function ($entries) use ($matcher, &$stats, $limit) {
                foreach ($entries as $entry) {
                    if (array_sum($stats) >= $limit) return false;
                    try {
                        $outcome = $matcher->match($entry);
                        $stats[$outcome] = ($stats[$outcome] ?? 0) + 1;
                    } catch (\Throwable $exception) {
                        $stats['failed']++;
                        $this->warn('Ledger entry '.$entry->id.' failed: '.$exception->getMessage());
                    }
                }
            });
        $this->info('Ledger matching: '.json_encode($stats));
        return $stats['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
