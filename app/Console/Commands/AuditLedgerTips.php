<?php

namespace App\Console\Commands;

use App\Models\Show;
use App\Models\WhatnotLedgerEntry;
use App\Models\WhatnotShowOrder;
use Illuminate\Console\Command;

class AuditLedgerTips extends Command
{
    protected $signature = 'whatnot:audit-ledger-tips {--limit=0 : Maximum tip rows to inspect (0 = all)}';
    protected $description = 'Read-only audit of imported Whatnot ledger tips against show orders and show times';

    public function handle(): int
    {
        $limit = max(0, (int) $this->option('limit'));
        $counts = ['total' => 0, 'exact_order' => 0, 'ambiguous_order' => 0, 'time_candidate' => 0, 'ambiguous_time' => 0, 'unmatched' => 0];
        $amounts = array_fill_keys(array_keys($counts), 0.0);
        $rows = [];
        $query = WhatnotLedgerEntry::query()->whereRaw('LOWER(TRIM(transaction_type)) IN (?, ?)', ['tip', 'tips'])->orderBy('id');
        if ($limit > 0) $query->limit($limit);

        foreach ($query->cursor() as $entry) {
            $counts['total']++;
            $amount = (float) ($entry->amount ?? 0);
            $amounts['total'] += $amount;
            $category = 'unmatched';
            $showId = null;
            $orderId = trim((string) $entry->whatnot_order_id);

            if ($orderId !== '' && $orderId !== '-') {
                $showIds = WhatnotShowOrder::query()
                    ->where('whatnot_order_id', $orderId)
                    ->whereHas('show', fn ($q) => $q->where('whatnot_channel_id', $entry->whatnot_channel_id))
                    ->whereNotNull('show_id')->distinct()->pluck('show_id')->all();
                if (count($showIds) === 1) {
                    $category = 'exact_order';
                    $showId = $showIds[0];
                } elseif (count($showIds) > 1) {
                    $category = 'ambiguous_order';
                }
            }

            if ($category === 'unmatched' && $entry->created_date && $entry->whatnot_channel_id) {
                // Candidate only: tips may be sent from a profile outside live shows.
                $matches = Show::query()
                    ->where('whatnot_channel_id', $entry->whatnot_channel_id)
                    ->whereNotNull('start_time')->whereNotNull('end_time')
                    ->where('start_time', '<=', $entry->created_date)
                    ->where('end_time', '>=', $entry->created_date)
                    ->pluck('id')->all();
                if (count($matches) === 1) {
                    $category = 'time_candidate';
                    $showId = $matches[0];
                } elseif (count($matches) > 1) {
                    $category = 'ambiguous_time';
                }
            }

            $counts[$category]++;
            $amounts[$category] += $amount;
            if (count($rows) < 50 && $category !== 'exact_order') {
                $rows[] = [$entry->id, $entry->whatnot_channel_id ?? '—', $entry->created_date?->toDateTimeString() ?? '—', '$'.number_format($amount, 2), $category, $showId ?? '—'];
            }
        }

        $this->table(['Category', 'Transactions', 'Signed amount'], array_map(
            fn ($key) => [$key, $counts[$key], '$'.number_format($amounts[$key], 2)],
            array_keys($counts)
        ));
        if ($rows) {
            $this->line('First 50 non-exact matches (tip ledger IDs only; no personal details):');
            $this->table(['Ledger ID', 'Channel ID', 'Created', 'Amount', 'Classification', 'Candidate show ID'], $rows);
        }
        $this->warn('READ ONLY: no show tips, ledger rows, payroll or payouts were modified.');
        $this->warn('Time matches are suggestions, not verified show attribution. Missing tip types/identifiers may require checking Whatnot Tips history or Weekly Orders report.');
        return self::SUCCESS;
    }
}
