<?php

namespace App\Console\Commands;

use App\Models\WhatnotLedgerEntry;
use App\Services\LedgerAttributionService;
use App\Services\LedgerAutoMatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class MatchExistingLedgerCommand extends Command
{
    protected $signature = 'ledger:auto-match {--channel= : Optional Whatnot channel ID} {--apply : Save verified matches; default is preview}';
    protected $description = 'Match existing ledger transactions to shows using verified source evidence. Does not change amounts or payroll.';

    public function handle(LedgerAttributionService $service, LedgerAutoMatcher $matcher): int
    {
        $channel = $this->option('channel');
        if ($channel !== null && (! ctype_digit((string) $channel) || (int) $channel < 1)) {
            $this->error('Channel must be a positive integer.');
            return self::FAILURE;
        }
        $apply = (bool) $this->option('apply');
        $counts = ['scanned' => 0, 'verified_show' => 0, 'matched' => 0, 'unmatched' => 0, 'manual' => 0, 'flagged' => 0, 'failed' => 0];
        $query = WhatnotLedgerEntry::query()->when($channel !== null, fn ($q) => $q->where('whatnot_channel_id', (int) $channel))->orderBy('id');
        $query->chunkById(200, function ($entries) use ($service, $matcher, $apply, &$counts) {
            foreach ($entries as $entry) {
                $counts['scanned']++;
                try {
                    $show = $service->suggestedShow($entry);
                    if ($show) $counts['verified_show']++;
                    if (! $apply) continue;
                    $outcome = $matcher->match($entry);
                    $counts[$outcome] = ($counts[$outcome] ?? 0) + 1;
                } catch (\Throwable $e) {
                    $counts['failed']++;
                    $this->warn('Ledger entry '.$entry->id.': '.$e->getMessage());
                }
            }
        });
        $this->line(json_encode(['mode' => $apply ? 'apply' : 'preview', 'counts' => $counts], JSON_PRETTY_PRINT));
        return $counts['failed'] ? self::FAILURE : self::SUCCESS;
    }
}
