<?php

namespace App\Console\Commands;

use App\Models\WhatnotLedgerEntry;
use App\Services\LedgerAttributionService;
use App\Services\LedgerAutoMatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class MatchExistingLedgerCommand extends Command
{
    protected $signature = 'ledger:auto-match {--channel= : Optional Whatnot channel ID} {--apply : Save verified matches; default is preview} {--since= : Inclusive completed date YYYY-MM-DD} {--until= : Inclusive completed date YYYY-MM-DD} {--limit=10000 : Maximum rows to inspect}';
    protected $description = 'Match existing ledger transactions to shows using verified source evidence. Does not change amounts or payroll.';

    public function handle(LedgerAttributionService $service, LedgerAutoMatcher $matcher): int
    {
        $channel = $this->option('channel');
        if ($channel !== null && (! ctype_digit((string) $channel) || (int) $channel < 1)) {
            $this->error('Channel must be a positive integer.');
            return self::FAILURE;
        }
        $apply = (bool) $this->option('apply');
        $since = $this->option('since');
        $until = $this->option('until');
        foreach (['since' => $since, 'until' => $until] as $name => $date) {
            if ($date !== null && (! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $date) ||
                ! \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $date) ||
                \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $date)->format('Y-m-d') !== $date)) {
                $this->error($name.' must be YYYY-MM-DD.');
                return self::FAILURE;
            }
        }
        if ($since && $until && $since > $until) {
            $this->error('since must be on or before until.');
            return self::FAILURE;
        }
        $limit = max(1, min(100000, (int) $this->option('limit')));
        $counts = ['scanned' => 0, 'verified_show' => 0, 'matched' => 0, 'unmatched' => 0, 'manual' => 0, 'flagged' => 0, 'failed' => 0];
        $query = WhatnotLedgerEntry::query()
            ->when($channel !== null, fn ($q) => $q->where('whatnot_channel_id', (int) $channel))
            ->when($since !== null, fn ($q) => $q->where('completed_date', '>=', $since.' 00:00:00'))
            ->when($until !== null, fn ($q) => $q->where('completed_date', '<', \Illuminate\Support\Carbon::parse($until)->addDay()->toDateString().' 00:00:00'))
            ->orderBy('id');
        $query->chunkById(200, function ($entries) use ($service, $matcher, $apply, $limit, &$counts) {
            foreach ($entries as $entry) {
                if ($counts['scanned'] >= $limit) return false;
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
