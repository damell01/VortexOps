<?php

namespace App\Console\Commands;

use App\Models\Show;
use App\Models\WhatnotLedgerEntry;
use App\Models\WhatnotShowOrder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DiagnoseTipOrderWindows extends Command
{
    protected $signature = 'whatnot:diagnose-tip-order-windows';
    protected $description = 'Read-only check of tip order identifiers and show-order activity windows';

    public function handle(): int
    {
        $tips = WhatnotLedgerEntry::query()->whereRaw('LOWER(TRIM(transaction_type)) IN (?, ?)', ['tip', 'tips']);
        $orders = WhatnotShowOrder::query();
        $this->table(['Metric', 'Count'], [
            ['Orders with linked show', (clone $orders)->whereNotNull('show_id')->count()],
            ['Orders with ordered timestamp', (clone $orders)->whereNotNull('ordered_at_whatnot')->count()],
            ['Linked orders with ordered timestamp', (clone $orders)->whereNotNull('show_id')->whereNotNull('ordered_at_whatnot')->count()],
            ['Shows with at least one timed order', WhatnotShowOrder::whereNotNull('show_id')->whereNotNull('ordered_at_whatnot')->distinct()->count('show_id')],
            ['Tips with populated order ID', (clone $tips)->whereNotNull('whatnot_order_id')->where('whatnot_order_id', '<>', '')->count()],
        ]);

        $tipRows = (clone $tips)->whereNotNull('whatnot_order_id')->where('whatnot_order_id', '<>', '')->get();
        $exact = 0; $ambiguous = 0; $differentChannel = 0; $missing = 0;
        foreach ($tipRows as $tip) {
            $ids = WhatnotShowOrder::query()->where('whatnot_order_id', trim((string) $tip->whatnot_order_id))
                ->whereNotNull('show_id')->distinct()->pluck('show_id');
            if ($ids->isEmpty()) { $missing++; continue; }
            $sameChannel = Show::query()->whereIn('id', $ids)->where('whatnot_channel_id', $tip->whatnot_channel_id)->pluck('id');
            if ($sameChannel->count() === 1) $exact++;
            elseif ($sameChannel->count() > 1) $ambiguous++;
            else $differentChannel++;
        }
        $this->table(['Order ID match classification', 'Tip count'], [
            ['Unique same-channel show', $exact],
            ['Multiple same-channel shows', $ambiguous],
            ['Only different-channel show', $differentChannel],
            ['No linked show order ID', $missing],
        ]);

        // Each row represents one show; bounds reflect sales activity, NOT actual stream times.
        $windows = DB::table('whatnot_show_orders')
            ->select('show_id', DB::raw('MIN(ordered_at_whatnot) AS first_order'), DB::raw('MAX(ordered_at_whatnot) AS last_order'))
            ->whereNotNull('show_id')->whereNotNull('ordered_at_whatnot')
            ->groupBy('show_id')->get()->keyBy('show_id');
        $showIds = $windows->keys()->all();
        $shows = Show::query()->whereIn('id', $showIds)->get(['id', 'whatnot_channel_id', 'show_date'])->keyBy('id');
        $byChannel = [];
        foreach ($windows as $showId => $window) {
            $channel = $shows->get($showId)?->whatnot_channel_id;
            if ($channel === null) continue;
            $byChannel[$channel][] = ['id' => $showId, 'start' => strtotime($window->first_order), 'end' => strtotime($window->last_order)];
        }
        $within = 0; $multiple = 0; $outside = 0; $amounts = ['single' => 0, 'multiple' => 0, 'outside' => 0];
        foreach ((clone $tips)->cursor() as $tip) {
            $timestamp = $tip->created_date?->timestamp;
            $matches = 0;
            if ($timestamp !== null) {
                foreach ($byChannel[$tip->whatnot_channel_id] ?? [] as $window) {
                    if ($timestamp >= $window['start'] && $timestamp <= $window['end']) $matches++;
                }
            }
            $amount = (float) ($tip->amount ?? 0);
            if ($matches === 1) { $within++; $amounts['single'] += $amount; }
            elseif ($matches > 1) { $multiple++; $amounts['multiple'] += $amount; }
            else { $outside++; $amounts['outside'] += $amount; }
        }
        $this->info('Tip timestamps inside observed first-to-last order windows (not proof of show attribution)');
        $this->table(['Category', 'Tip rows', 'Signed total'], [
            ['One order activity window', $within, '$'.number_format($amounts['single'], 2)],
            ['Multiple order activity windows', $multiple, '$'.number_format($amounts['multiple'], 2)],
            ['Outside all order activity windows', $outside, '$'.number_format($amounts['outside'], 2)],
        ]);
        $this->warn('Read only. Order activity windows do not represent actual show start/end. No automatic attribution is authorized.');
        return self::SUCCESS;
    }
}
