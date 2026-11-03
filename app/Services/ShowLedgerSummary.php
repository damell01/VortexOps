<?php

namespace App\Services;

use App\Models\Show;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The money the ledger records for one show: sales, refunds, shipping,
 * carrier adjustments and tips, split into what happened by the end of the
 * show and what arrived afterwards ("this show lost $40 to a refund last
 * week").
 *
 * An entry belongs to the show through its ledger attribution (the auto
 * matcher's or a reviewed one). Entries that carry this show's Whatnot ID but
 * are not matched yet are listed too, marked as waiting. The show's Whatnot
 * net is not changed here: the ledger is shown beside it.
 */
final class ShowLedgerSummary
{
    /** @return array<string,mixed> */
    public function for(Show $show): array
    {
        $attributed = DB::table('ledger_attributions as a')
            ->join('whatnot_ledger_entries as e', 'e.id', '=', 'a.whatnot_ledger_entry_id')
            ->where('a.show_id', $show->id)->whereIn('a.status', ['approved', 'pending'])
            ->get(['e.id', 'e.amount', 'e.transaction_type', 'e.message', 'e.completed_date', 'e.created_date', 'e.whatnot_order_id', 'a.category', 'a.status']);

        $waiting = collect();
        if (filled($show->whatnot_show_id)) {
            $waiting = DB::table('whatnot_ledger_entries as e')
                ->leftJoin('ledger_attributions as a', 'a.whatnot_ledger_entry_id', '=', 'e.id')
                ->whereNull('a.id')
                ->where('e.whatnot_channel_id', $show->whatnot_channel_id)
                ->where('e.raw_data', 'like', '%"livestream_id":"'.strtolower((string) $show->whatnot_show_id).'"%')
                ->get(['e.id', 'e.amount', 'e.transaction_type', 'e.message', 'e.completed_date', 'e.created_date', 'e.whatnot_order_id'])
                ->map(fn ($row) => (object) ((array) $row + ['category' => null, 'status' => 'waiting']));
        }

        $end = $this->showEnd($show);
        $categories = app(LedgerAttributionService::class);
        $lines = $attributed->concat($waiting)->map(function ($row) use ($end, $categories) {
            $at = $row->completed_date ?? $row->created_date;
            $type = strtolower(trim((string) $row->transaction_type));
            $category = $row->category ?: $categories->suggestedCategory(new \App\Models\WhatnotLedgerEntry(['transaction_type' => $row->transaction_type]));
            if (str_contains($type, 'carrier')) $category = 'carrier adjustments';

            return [
                'id' => $row->id,
                'at' => $at ? Carbon::parse($at) : null,
                'amount' => round((float) $row->amount, 2),
                'type' => (string) $row->transaction_type,
                'category' => $category,
                'message' => $row->message,
                'order' => $row->whatnot_order_id,
                'status' => $row->status,
                'after_show' => $end !== null && $at !== null && Carbon::parse($at)->gt($end),
            ];
        })->sortBy(fn ($l) => $l['at']?->timestamp ?? 0)->values();

        $sum = fn (Collection $set, ?callable $where = null) => round((float) $set->filter($where ?? fn () => true)->sum('amount'), 2);
        $isTip = fn ($l) => $l['category'] === 'tips';
        $money = $lines->reject($isTip);
        $after = $lines->where('after_show', true);

        return [
            'lines' => $lines,
            'after_show' => $after->values(),
            'ledger_net' => $sum($money),
            'tips' => $sum($lines->filter($isTip)),
            'by_category' => $money->groupBy('category')->map(fn ($set) => round((float) $set->sum('amount'), 2))->all(),
            'after_show_net' => $sum($after->reject($isTip)),
            'after_show_tips' => $sum($after->filter($isTip)),
            'waiting' => $lines->where('status', 'waiting')->count(),
            'show_ended_at' => $end,
        ];
    }

    /** When the show ended, in app time: its end time on the show date, else that day's end. */
    private function showEnd(Show $show): ?Carbon
    {
        if (! $show->show_date) return null;
        $tz = (string) config('vortex.whatnot.timezone', 'America/Chicago');
        $date = $show->show_date->toDateString();
        $end = $show->end_time
            ? Carbon::parse($date.' '.Carbon::parse((string) $show->getRawOriginal('end_time'))->format('H:i:s'), $tz)
            : Carbon::parse($date, $tz)->endOfDay();
        if ($show->start_time && $show->end_time && $end->format('H:i:s') < Carbon::parse((string) $show->getRawOriginal('start_time'))->format('H:i:s')) {
            $end->addDay(); // ran past midnight
        }

        return $end->setTimezone(config('app.timezone'));
    }
}
