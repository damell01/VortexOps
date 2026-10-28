<?php

namespace App\Services;

use App\Models\Business;
use App\Models\WhatnotChannel;
use App\Models\WhatnotLedgerEntry;
use App\Support\PaySchedule;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Read-only imported tips. Never allocates tips or changes payroll. */
class LedgerTipReport
{
    public function between(string $from, string $until, string $channel = 'all', string $business = 'all', bool $channelContext = true): array
    {
        // Channels are shared by businesses. Only an exact order on one show,
        // with all its streamers assigned to the same business, can attribute
        // a ledger tip. No time-window or channel-ownership guesses.
        // Only explicitly approved human-reviewed show attributions can
        // allocate tips to a business. Imported order/show links are untrusted.
        $attribution = DB::table('ledger_attributions as verified')
            ->join('shows', 'shows.id', '=', 'verified.show_id')
            ->selectRaw('verified.whatnot_ledger_entry_id, verified.business_id')
            ->where('verified.status', 'approved')
            ->whereNotNull('verified.submitted_by')
            ->where('verified.target', 'show')
            ->whereColumn('verified.business_id', '!=', DB::raw('0'));

        $query = WhatnotLedgerEntry::query()
            ->when($channelContext, fn ($q) => $q->inChannelContext())
            ->leftJoinSub($attribution, 'attribution', function (JoinClause $join) {
                $join->on('attribution.whatnot_ledger_entry_id', '=', 'whatnot_ledger_entries.id');
            })
            ->whereRaw('LOWER(TRIM(transaction_type)) IN (?, ?)', ['tip', 'tips'])
            ->where('created_date', '>=', Carbon::parse($from)->startOfDay())
            ->where('created_date', '<', Carbon::parse($until)->startOfDay()->addDay())
            ->when($channel === 'unassigned', fn ($q) => $q->whereNull('whatnot_ledger_entries.whatnot_channel_id'))
            ->when(ctype_digit($channel), fn ($q) => $q->where('whatnot_ledger_entries.whatnot_channel_id', (int) $channel));

        $daily = $query->toBase()->selectRaw('DATE(created_date) as day, whatnot_ledger_entries.whatnot_channel_id as channel_id, attribution.business_id')
            ->selectRaw('SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as credits')
            ->selectRaw('SUM(CASE WHEN amount < 0 THEN -amount ELSE 0 END) as debits')
            ->selectRaw('COUNT(*) as entries, SUM(CASE WHEN amount IS NULL THEN 1 ELSE 0 END) as missing_amounts')
            ->selectRaw("SUM(CASE WHEN LOWER(TRIM(status)) IN ('pending', 'processing') THEN 1 ELSE 0 END) as pending")
            ->groupByRaw('DATE(created_date), whatnot_ledger_entries.whatnot_channel_id, attribution.business_id')
            ->orderBy('day')->get();

        $channels = WhatnotChannel::query()->pluck('name', 'id');
        $businesses = Business::query()->pluck('name', 'id');
        $rows = $weeks = $days = $unassigned = [];
        $total = $this->emptyTotals();
        $unassignedTotal = $this->emptyTotals();
        foreach ($daily as $day) {
            $unknown = $day->business_id === null;
            $matches = $business === 'all' || ($business === 'unassigned' && $unknown)
                || (ctype_digit($business) && (int) $day->business_id === (int) $business);
            $key = ($day->channel_id ?? 'unassigned').':'.($day->business_id ?? 'unassigned');
            $labels = [
                'channel' => $channels[$day->channel_id] ?? 'Unassigned channel',
                'business' => $businesses[$day->business_id] ?? 'Unassigned business',
            ];
            if (! $matches) {
                // Unknown business tips stay visible beside a business filter,
                // separately from its attributed total, so they cannot be lost
                // or mistaken for that business's payable amount.
                if ($unknown && ctype_digit($business)) {
                    $unassigned[$key] ??= $labels + $this->emptyTotals();
                    $this->add($unassigned[$key], $day);
                    $this->add($unassignedTotal, $day);
                }

                continue;
            }
            $rows[$key] ??= $labels + $this->emptyTotals();
            $this->add($rows[$key], $day);
            $this->add($total, $day);
            $days[$day->day] ??= ['day' => $day->day] + $this->emptyTotals();
            $this->add($days[$day->day], $day);
            $week = Carbon::parse($day->day)->startOfWeek(PaySchedule::startDay())->toDateString();
            $weekKey = $week.':'.$key;
            $weeks[$weekKey] ??= ['week' => $week] + $labels + $this->emptyTotals();
            $this->add($weeks[$weekKey], $day);
        }

        return ['rows' => array_values($rows), 'weeks' => array_values($weeks), 'days' => array_values($days), 'total' => $total,
            'unassigned' => array_values($unassigned), 'unassigned_total' => $unassignedTotal];
    }

    /**
     * Read-only verified tip totals for shows with exactly one assigned streamer.
     * Shared shows, duplicate order matches and missing streamer links remain
     * unattributed; never distribute their amounts automatically.
     */
    public function byStreamer(string $from, string $until, string $channel = 'all'): array
    {
        // Exactly one matched show and exactly one primary streamer. Approved
        // manual decisions take precedence over this automatic attribution.

        return WhatnotLedgerEntry::query()
            ->inChannelContext()
            ->leftJoin('ledger_attributions as manual', function (JoinClause $join) {
                $join->on('manual.whatnot_ledger_entry_id', '=', 'whatnot_ledger_entries.id')
                    ->where('manual.status', '=', 'approved')
                    ->whereNotNull('manual.submitted_by');
            })
            ->join('streamers', function (JoinClause $join) {
                $join->on('streamers.id', '=', DB::raw("manual.streamer_id"));
            })
            ->whereRaw('LOWER(TRIM(whatnot_ledger_entries.transaction_type)) IN (?, ?)', ['tip', 'tips'])
            ->where('whatnot_ledger_entries.created_date', '>=', Carbon::parse($from)->startOfDay())
            ->where('whatnot_ledger_entries.created_date', '<', Carbon::parse($until)->startOfDay()->addDay())
            ->when(ctype_digit($channel), fn ($q) => $q->where('whatnot_ledger_entries.whatnot_channel_id', (int) $channel))
            ->when($channel === 'unassigned', fn ($q) => $q->whereNull('whatnot_ledger_entries.whatnot_channel_id'))
            ->selectRaw('streamers.id as streamer_id, streamers.name as streamer_name, streamers.business_id, COUNT(*) as entries')
            ->selectRaw('SUM(CASE WHEN whatnot_ledger_entries.amount > 0 THEN whatnot_ledger_entries.amount ELSE 0 END) as credits')
            ->selectRaw('SUM(CASE WHEN whatnot_ledger_entries.amount < 0 THEN -whatnot_ledger_entries.amount ELSE 0 END) as debits')
            ->groupBy('streamers.id', 'streamers.name', 'streamers.business_id')
            ->orderBy('streamers.name')
            ->get()
            ->map(fn ($row) => [
                'streamer' => $row->streamer_name,
                'business_id' => $row->business_id,
                'entries' => (int) $row->entries,
                'credits' => (int) round((float) $row->credits * 100),
                'debits' => (int) round((float) $row->debits * 100),
                'net' => (int) round(((float) $row->credits - (float) $row->debits) * 100),
            ])->all();
    }

    private function emptyTotals(): array
    {
        return ['credits' => 0, 'debits' => 0, 'net' => 0, 'entries' => 0, 'pending' => 0, 'missing_amounts' => 0];
    }

    private function add(array &$totals, object $day): void
    {
        // Accumulate integer cents, including negative corrections.
        $totals['credits'] += (int) round((float) $day->credits * 100);
        $totals['debits'] += (int) round((float) $day->debits * 100);
        $totals['net'] = $totals['credits'] - $totals['debits'];
        foreach (['entries', 'pending', 'missing_amounts'] as $field) {
            $totals[$field] += (int) $day->{$field};
        }
    }
}
