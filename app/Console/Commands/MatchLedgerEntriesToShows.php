<?php

namespace App\Console\Commands;

use App\Models\WhatnotLedgerEntry;
use App\Services\LedgerAutoMatcher;
use Illuminate\Console\Command;

class MatchLedgerEntriesToShows extends Command
{
    protected $signature = 'ledger:match-shows {--limit=1000 : Maximum ledger entries to inspect}';
    protected $description = 'Link ledger transactions to shows by unique exact Whatnot order ID and channel';

    public function handle(LedgerAutoMatcher $matcher): int
    {
        $limit = min(100000, max(1, (int) $this->option('limit')));
        $stats = ['matched' => 0, 'unmatched' => 0, 'manual' => 0];
        WhatnotLedgerEntry::query()->whereNotNull('whatnot_order_id')
            ->whereNotIn('whatnot_order_id', ['', '-'])
            ->orderBy('id')->chunkById(500, function ($entries) use ($matcher, &$stats, $limit) {
                foreach ($entries as $entry) {
                    if (array_sum($stats) >= $limit) return false;
                    $stats[$matcher->match($entry)]++;
                }
            });
        $this->info('Ledger matching: '.json_encode($stats));
        return self::SUCCESS;
    }
}
