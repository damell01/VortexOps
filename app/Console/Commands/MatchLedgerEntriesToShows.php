<?php

namespace App\Console\Commands;

use App\Models\WhatnotLedgerEntry;
use App\Services\LedgerAutoMatcher;
use Illuminate\Console\Command;

class MatchLedgerEntriesToShows extends Command
{
    protected $signature = 'ledger:match-shows {--limit=1000 : Maximum ledger entries to inspect} {--all : Re-check every entry, not only the ones still open or recent}';
    protected $description = 'Link ledger transactions to shows using verified order evidence or explicit source livestream IDs';

    public function handle(LedgerAutoMatcher $matcher): int
    {
        $limit = min(100000, max(1, (int) $this->option('limit')));
        $stats = ['matched' => 0, 'categorized' => 0, 'unmatched' => 0, 'manual' => 0, 'flagged' => 0, 'failed' => 0];
        // The hourly run only looks at entries that can still change: no
        // attribution yet, an automatic suggestion nobody has decided on, or an
        // entry imported/updated in the last week (new order evidence). Settled
        // entries (approved or decided by a person) are skipped; --all re-checks
        // everything, as every run used to (30+ minutes of CPU each hour).
        $since = now()->subDays(7);
        WhatnotLedgerEntry::query()
            ->when(! $this->option('all'), fn ($q) => $q->where(fn ($w) => $w
                ->whereNotExists(fn ($e) => $e->selectRaw('1')->from('ledger_attributions as la')->whereColumn('la.whatnot_ledger_entry_id', 'whatnot_ledger_entries.id'))
                ->orWhereExists(fn ($e) => $e->selectRaw('1')->from('ledger_attributions as la')->whereColumn('la.whatnot_ledger_entry_id', 'whatnot_ledger_entries.id')
                    ->where('la.status', '!=', 'approved')->whereNull('la.submitted_by')->whereNull('la.reviewed_by'))
                ->orWhere('whatnot_ledger_entries.updated_at', '>=', $since)
                ->orWhere('whatnot_ledger_entries.created_at', '>=', $since)))
            ->orderBy('id')->chunkById(500, function ($entries) use ($matcher, &$stats, $limit) {
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
