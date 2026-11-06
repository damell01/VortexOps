<?php

namespace App\Console\Commands;

use App\Models\WhatnotLedgerEntry;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class AuditWhatnotLedgerDuplicates extends Command
{
    protected $signature = 'whatnot:audit-ledger-duplicates {--channel= : Optional channel ID} {--limit=2000 : Maximum duplicate groups per channel to inspect} {--examples=8 : Maximum example pairs to print per channel}';
    protected $description = 'Read-only audit of historical scraper ledger rows with matching order, amount, type and five-hour timestamp offsets.';

    public function handle(): int
    {
        $limit = max(1, min(20000, (int) $this->option('limit')));
        $examples = max(0, min(30, (int) $this->option('examples')));
        $channels = $this->option('channel') !== null ? [(int) $this->option('channel')] : [1, 2, 3, 4];

        foreach ($channels as $channel) {
            $groups = WhatnotLedgerEntry::query()
                ->where('whatnot_channel_id', $channel)
                ->whereNotNull('whatnot_order_id')
                ->where('whatnot_order_id', '<>', '')
                ->where(fn ($q) => $q->whereNull('dedup_key')->orWhere('dedup_key', 'not like', 'wn_api:%'))
                ->selectRaw('whatnot_order_id, amount, transaction_type, COUNT(*) as copies')
                ->groupBy('whatnot_order_id', 'amount', 'transaction_type')
                ->havingRaw('COUNT(*) > 1')
                ->orderBy('whatnot_order_id')
                ->limit($limit + 1)
                ->get();

            $truncated = $groups->count() > $limit;
            $summary = ['channel' => $channel, 'groups_examined' => min($groups->count(), $limit),
                'truncated' => $truncated, 'five_hour_pairs' => 0,
                'five_hour_pairs_with_matching_completed_offset' => 0,
                'five_hour_pairs_with_missing_completed' => 0,
                'pairs_with_attribution' => 0, 'pairs_with_manual_attribution' => 0,
                'other_duplicate_groups' => 0, 'examples' => []];

            foreach ($groups->take($limit) as $group) {
                $rows = WhatnotLedgerEntry::query()
                    ->where('whatnot_channel_id', $channel)
                    ->where('whatnot_order_id', $group->whatnot_order_id)
                    ->where('amount', $group->amount)
                    ->where('transaction_type', $group->transaction_type)
                    ->where(fn ($q) => $q->whereNull('dedup_key')->orWhere('dedup_key', 'not like', 'wn_api:%'))
                    ->orderBy('id')->limit(20)->get();
                $pairs = [];
                for ($i = 0; $i < $rows->count(); $i++) {
                    for ($j = $i + 1; $j < $rows->count(); $j++) {
                        $a = $rows[$i];
                        $b = $rows[$j];
                        if (!$a->created_date || !$b->created_date ||
                            abs($a->created_date->diffInSeconds($b->created_date)) !== 18000) continue;
                        $pairs[] = [$a, $b];
                    }
                }
                if ($pairs === []) {
                    $summary['other_duplicate_groups']++;
                    continue;
                }
                foreach ($pairs as [$a, $b]) {
                    $summary['five_hour_pairs']++;
                    if (!$a->completed_date || !$b->completed_date) {
                        $summary['five_hour_pairs_with_missing_completed']++;
                    } elseif (abs($a->completed_date->diffInSeconds($b->completed_date)) === 18000) {
                        $summary['five_hour_pairs_with_matching_completed_offset']++;
                    }
                    $attributions = DB::table('ledger_attributions')
                        ->whereIn('whatnot_ledger_entry_id', [$a->id, $b->id])
                        ->get(['whatnot_ledger_entry_id', 'status', 'submitted_by', 'show_id', 'streamer_id', 'business_id']);
                    if ($attributions->isNotEmpty()) $summary['pairs_with_attribution']++;
                    if ($attributions->contains(fn ($v) => $v->submitted_by !== null)) $summary['pairs_with_manual_attribution']++;
                    if (count($summary['examples']) < $examples) {
                        $summary['examples'][] = [
                            'order' => $group->whatnot_order_id, 'amount' => $group->amount,
                            'type' => $group->transaction_type,
                            'ids' => [$a->id, $b->id],
                            'created' => [$a->created_date?->toDateTimeString(), $b->created_date?->toDateTimeString()],
                            'completed' => [$a->completed_date?->toDateTimeString(), $b->completed_date?->toDateTimeString()],
                            'attributions' => $attributions->toArray(),
                        ];
                    }
                }
            }
            $this->line(json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }
        $this->info('Read-only audit complete. No ledger rows or allocations were modified.');
        return self::SUCCESS;
    }
}
