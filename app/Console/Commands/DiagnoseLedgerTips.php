<?php

namespace App\Console\Commands;

use App\Models\Show;
use App\Models\WhatnotLedgerEntry;
use App\Models\WhatnotShowOrder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DiagnoseLedgerTips extends Command
{
    protected $signature = 'whatnot:diagnose-ledger-tips';
    protected $description = 'Read-only diagnostics for ledger tip identifiers, order coverage, and show time coverage';

    public function handle(): int
    {
        $tips = WhatnotLedgerEntry::query()->whereRaw('LOWER(TRIM(transaction_type)) IN (?, ?)', ['tip', 'tips']);
        $this->info('Ledger tip attribution diagnostics (read only)');
        $this->table(['Metric', 'Count'], [
            ['Ledger entries (all types)', WhatnotLedgerEntry::count()],
            ['Tip entries', (clone $tips)->count()],
            ['Tips with order ID', (clone $tips)->whereNotNull('whatnot_order_id')->where('whatnot_order_id', '<>', '')->count()],
            ['Tips with order hash', (clone $tips)->whereNotNull('order_hash')->where('order_hash', '<>', '')->count()],
            ['Tips with listing ID', (clone $tips)->whereNotNull('listing_id')->where('listing_id', '<>', '')->count()],
            ['Tips with channel', (clone $tips)->whereNotNull('whatnot_channel_id')->count()],
            ['Tips with created date', (clone $tips)->whereNotNull('created_date')->count()],
            ['Shows (all)', Show::count()],
            ['Shows with start and end time', Show::whereNotNull('start_time')->whereNotNull('end_time')->count()],
            ['Shows with positive analytics tips', Show::where('tips', '>', 0)->count()],
            ['Whatnot show orders', WhatnotShowOrder::count()],
            ['Show orders with order ID', WhatnotShowOrder::whereNotNull('whatnot_order_id')->where('whatnot_order_id', '<>', '')->count()],
        ]);

        $this->info('Ledger transaction types (all entries)');
        $this->table(['Type', 'Rows'], WhatnotLedgerEntry::query()
            ->select('transaction_type', DB::raw('COUNT(*) AS total'))
            ->groupBy('transaction_type')->orderByDesc('total')->limit(30)
            ->get()->map(fn ($row) => [$row->transaction_type ?? '(null)', $row->total])->all());

        $this->info('Tip counts by channel (IDs only)');
        $this->table(['Channel ID', 'Tip rows', 'Signed total'], (clone $tips)
            ->select('whatnot_channel_id', DB::raw('COUNT(*) AS total'), DB::raw('SUM(amount) AS amount_total'))
            ->groupBy('whatnot_channel_id')->orderBy('whatnot_channel_id')
            ->get()->map(fn ($row) => [$row->whatnot_channel_id ?? '(null)', $row->total, '$'.number_format((float) $row->amount_total, 2)])->all());

        $this->info('Show timing coverage by channel');
        $this->table(['Channel ID', 'Shows', 'With both times', 'Positive show tips'], Show::query()
            ->select('whatnot_channel_id', DB::raw('COUNT(*) AS total'),
                DB::raw('SUM(CASE WHEN start_time IS NOT NULL AND end_time IS NOT NULL THEN 1 ELSE 0 END) AS timed'),
                DB::raw('SUM(CASE WHEN tips > 0 THEN 1 ELSE 0 END) AS tipped'))
            ->groupBy('whatnot_channel_id')->orderBy('whatnot_channel_id')
            ->get()->map(fn ($row) => [$row->whatnot_channel_id ?? '(null)', $row->total, $row->timed, $row->tipped])->all());

        $this->info('Raw ledger tip keys (first 10 records, key names only)');
        $keys = [];
        foreach ((clone $tips)->orderBy('id')->limit(10)->get() as $tip) {
            foreach (array_keys(is_array($tip->raw_data) ? $tip->raw_data : []) as $key) $keys[$key] = true;
        }
        $this->line($keys ? implode(', ', array_keys($keys)) : '(no raw data keys found)');

        $this->warn('No database records changed. Dates and identifiers were not printed to avoid exposing buyer data.');
        return self::SUCCESS;
    }
}
