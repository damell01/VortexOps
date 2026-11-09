<?php

namespace App\Console\Commands;

use App\Models\WhatnotLedgerEntry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Read-only reconciliation of buyer tips and approved show/streamer ownership.
 * Does not infer a show from timestamps, amounts, or mutable streamer profiles.
 */
class AuditTipAttributionIntegrity extends Command
{
    protected $signature = 'whatnot:audit-tip-attribution {--limit=30 : Maximum example rows per issue}';
    protected $description = 'Read-only check for tip attribution exceptions and primary-streamer mismatches';

    public function handle(): int
    {
        $limit = max(0, min(200, (int) $this->option('limit')));
        $counts = array_fill_keys([
            'total_tips', 'unassigned', 'pending', 'approved_show', 'approved_house',
            'approved_without_show', 'approved_without_streamer', 'primary_mismatch',
            'missing_primary', 'multiple_primary', 'category_mismatch',
        ], 0);
        $examples = [];

        WhatnotLedgerEntry::query()
            ->whereRaw('LOWER(TRIM(transaction_type)) IN (?, ?)', ['tip', 'tips'])
            ->select(['id', 'amount', 'transaction_type'])
            ->orderBy('id')
            ->chunkById(500, function ($entries) use (&$counts, &$examples, $limit) {
                $ids = $entries->pluck('id')->all();
                $attributions = DB::table('ledger_attributions')
                    ->whereIn('whatnot_ledger_entry_id', $ids)
                    ->get()->keyBy('whatnot_ledger_entry_id');
                $showIds = $attributions->pluck('show_id')->filter()->unique()->values()->all();
                $primaryByShow = DB::table('show_streamer')
                    ->whereIn('show_id', $showIds)->where('is_primary', true)
                    ->get(['show_id', 'streamer_id'])->groupBy('show_id');

                foreach ($entries as $entry) {
                    $counts['total_tips']++;
                    $a = $attributions->get($entry->id);
                    $issues = [];
                    if (! $a || $a->target === 'unassigned') {
                        $counts['unassigned']++;
                        $issues[] = 'unassigned';
                    } elseif ($a->status !== 'approved') {
                        $counts['pending']++;
                        $issues[] = 'pending';
                    }
                    if (! $a) {
                        $this->captureExample($examples, $entry->id, $issues, $limit);
                        continue;
                    }
                    if ($a->category !== 'tips') {
                        $counts['category_mismatch']++;
                        $issues[] = 'category_mismatch';
                    }
                    if ($a->status === 'approved') {
                        if ($a->target === 'house') {
                            $counts['approved_house']++;
                            $issues[] = 'approved_house';
                        }
                        if ($a->target !== 'show' || ! $a->show_id) {
                            $counts['approved_without_show']++;
                            $issues[] = 'approved_without_show';
                        }
                        if (! $a->streamer_id) {
                            $counts['approved_without_streamer']++;
                            $issues[] = 'approved_without_streamer';
                        }
                        if ($a->target === 'show' && $a->show_id && $a->streamer_id) {
                            $primaries = $primaryByShow->get($a->show_id, collect());
                            if ($primaries->count() === 0) {
                                $counts['missing_primary']++;
                                $issues[] = 'missing_primary';
                            } elseif ($primaries->count() > 1) {
                                $counts['multiple_primary']++;
                                $issues[] = 'multiple_primary';
                            } elseif ((int) $primaries->first()->streamer_id !== (int) $a->streamer_id) {
                                $counts['primary_mismatch']++;
                                $issues[] = 'primary_mismatch';
                            } else {
                                $counts['approved_show']++;
                            }
                        }
                    }
                    $this->captureExample($examples, $entry->id, $issues, $limit);
                }
            });

        $this->table(['Check', 'Count'], collect($counts)->map(fn ($count, $key) => [$key, $count])->values()->all());
        if ($examples) {
            $this->table(['Ledger entry ID', 'Review reason(s)'], $examples);
        }
        $this->warn('READ ONLY. No attributions, ledger transactions, payroll or pay runs were changed.');
        $this->warn('A primary-streamer mismatch may reflect an approved historical assignment; investigate before correcting.');
        return self::SUCCESS;
    }

    private function captureExample(array &$examples, int $entryId, array $issues, int $limit): void
    {
        if ($issues && count($examples) < $limit) {
            $examples[] = [$entryId, implode(', ', $issues)];
        }
    }
}
