<?php

namespace App\Services;

use App\Models\WhatnotLedgerEntry;
use App\Services\LedgerAutoMatcher;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Import the Seller API financial report (seller_financial_report CSV,
 * developers.whatnot.com/reports) into whatnot_ledger_entries — the table
 * the ledger page, tips report, attribution and payroll read.
 *
 *  - Each row is keyed by Whatnot's ledger_transaction_id, so re-importing
 *    the same window never adds a row twice.
 *  - Types become the labels the existing code reads: TIP → "Tip" (the tips
 *    report matches tip/tips), ORDER_EARNINGS → "Order earnings" (a sale to
 *    the categoriser), refunds and shipping likewise.
 *  - livestream_id is kept in raw_data, the key LedgerAutoMatcher /
 *    LedgerAttributionService already use to find the show and streamer.
 *  - No double counting with the scraper: a scraper row for the same
 *    transaction (same order and amount; for tips, same amount within a few
 *    minutes) is linked, not copied. Several candidates → skipped, reported.
 *  - Rows completed before WHATNOT_SELLER_LEDGER_START are not created, so
 *    the switch from scraper to API happens on a date the owner chooses.
 */
final class WhatnotApiLedgerImporter
{
    public const REQUIRED_COLUMNS = ['ledger_transaction_id', 'transaction_type', 'transaction_amount', 'transaction_currency', 'transaction_completed_at_utc'];

    private const TIP_MATCH_MINUTES = 3;

    /** Columns not kept: buyer address details are not needed here. */
    private const DROP = ['buyer_state', 'buyer_country'];

    /**
     * @return array{counts:array<string,int>, dry_run:bool}
     */
    public function import(int $channelId, string $csv, bool $dryRun = true, ?Carbon $backfillFrom = null): array
    {
        if (! $dryRun && ! WhatnotApiOrderApplier::writesAllowed()) {
            throw new RuntimeException('Applying API data is disabled.');
        }
        $rows = $this->parse($csv);
        $start = $this->ledgerStart();
        $counts = ['received' => count($rows), 'created' => 0, 'linked' => 0, 'unchanged' => 0,
            'ambiguous' => 0, 'before_start' => 0, 'covered_day_unmatched' => 0, 'invalid' => 0, 'tips' => 0,
            'show_via_shipment' => 0, 'matched_to_show' => 0,
            'tips_api_with_livestream' => 0, 'tips_api_with_order_id' => 0,
            'tips_linked' => 0, 'tips_ambiguous' => 0, 'tips_before_start' => 0,
            'tips_created' => 0, 'tips_unchanged' => 0, 'tips_attributed' => 0,
            'tips_with_known_show' => 0, 'tips_missing_show' => 0,
            'tips_nearby_scraper_same_amount' => 0, 'tips_nearby_scraper_other_amount' => 0];
        $matcher = app(LedgerAutoMatcher::class);
        $coveredDays = [];
        // Load previously reconciled IDs once rather than scanning the JSON
        // column thousands of times (one full-table scan per report row).
        $linkedByApiId = [];
        WhatnotLedgerEntry::query()->where('whatnot_channel_id', $channelId)
            ->whereNotNull('raw_data')->select(['id', 'raw_data'])
            ->chunkById(1000, function ($entries) use (&$linkedByApiId) {
                foreach ($entries as $existing) {
                    $id = $existing->raw_data['ledger_transaction_id'] ?? null;
                    if (! is_string($id) || $id === '') continue;
                    $linkedByApiId[$id] ??= collect();
                    if ($linkedByApiId[$id]->count() < 2) $linkedByApiId[$id]->push($existing->id);
                }
            });

        $run = function () use ($rows, $channelId, $dryRun, $start, $backfillFrom, $matcher, $linkedByApiId, &$counts, &$coveredDays) {
            foreach ($rows as $row) {
                $entry = $this->toEntry($channelId, $row);
                if ($entry === null) {
                    $counts['invalid']++;
                    continue;
                }
                $isTip = strcasecmp($entry['transaction_type'], 'Tip') === 0;
                if ($isTip) {
                    $counts['tips']++;
                    if (filled($entry['raw_data']['livestream_id'] ?? null)) $counts['tips_api_with_livestream']++;
                    if (filled($entry['whatnot_order_id'])) $counts['tips_api_with_order_id']++;
                    $live = $entry['raw_data']['livestream_id'] ?? null;
                    if (is_string($live) && preg_match('/^[a-f0-9-]{32,36}$/i', $live)) {
                        $known = \App\Models\Show::query()->where('whatnot_channel_id', $channelId)
                            ->whereRaw('LOWER(whatnot_show_id) = ?', [strtolower($live)])->exists();
                        $counts[$known ? 'tips_with_known_show' : 'tips_missing_show']++;
                    }
                }
                if ($this->showViaShipment($channelId, $entry)) $counts['show_via_shipment']++;

                $existingApiEntry = WhatnotLedgerEntry::where('whatnot_channel_id', $channelId)
                    ->where('dedup_key', $entry['dedup_key'])->first();
                if ($existingApiEntry !== null) {
                    $counts['unchanged']++;
                    if ($isTip) $counts['tips_unchanged']++;
                    // A previous import may have preceded show/order/shipments
                    // backfill. Retry attribution without changing the financial
                    // transaction or overriding a human-approved allocation.
                    if (! $dryRun && $matcher->match($existingApiEntry) === 'matched') {
                        $counts['matched_to_show']++;
                        if ($isTip) $counts['tips_attributed']++;
                    }
                    continue;
                }

                // Prefer an authoritative Seller API transaction ID over any
                // amount/time heuristic. Scraper imports may already carry
                // this ID from an earlier reconciliation.
                $apiId = (string) ($entry['raw_data']['ledger_transaction_id'] ?? '');
                $idMatches = $apiId !== '' && isset($linkedByApiId[$apiId])
                    ? $linkedByApiId[$apiId]
                    : collect();
                if ($idMatches->count() > 1) {
                    $counts['ambiguous']++;
                    if ($isTip) $counts['tips_ambiguous']++;
                    continue;
                }
                $candidates = $idMatches->isNotEmpty()
                    ? WhatnotLedgerEntry::query()->whereKey($idMatches->all())->get()
                    : $this->scraperMatches($channelId, $entry);
                if ($isTip && $candidates->isEmpty()) {
                    // Diagnostic only: do not associate tips by approximate time or
                    // amount. This reveals scraper/API discrepancies safely.
                    $at = Carbon::parse($entry['completed_date']);
                    $nearby = WhatnotLedgerEntry::query()
                        ->where('whatnot_channel_id', $channelId)
                        ->whereRaw('LOWER(TRIM(transaction_type)) IN (?, ?)', ['tip', 'tips'])
                        ->where(fn ($q) => $q->whereNull('dedup_key')->orWhere('dedup_key', 'not like', 'wn_api:%'))
                        ->where(fn ($q) => $q->whereBetween('completed_date', [$at->copy()->subMinutes(30), $at->copy()->addMinutes(30)])
                            ->orWhereBetween('created_date', [$at->copy()->subMinutes(30), $at->copy()->addMinutes(30)]))
                        ->get(['amount']);
                    if ($nearby->contains(fn ($row) => abs((float) $row->amount - (float) $entry['amount']) < 0.005)) {
                        $counts['tips_nearby_scraper_same_amount']++;
                    } elseif ($nearby->isNotEmpty()) {
                        $counts['tips_nearby_scraper_other_amount']++;
                    }
                }
                if ($candidates->count() > 1) {
                    $counts['ambiguous']++;
                    if ($isTip) $counts['tips_ambiguous']++;
                    continue;
                }
                if ($candidates->count() === 1) {
                    $counts['linked']++;
                    if ($isTip) $counts['tips_linked']++;
                    if (! $dryRun) {
                        $existing = $candidates->first();
                        $raw = is_array($existing->raw_data) ? $existing->raw_data : [];
                        $raw['ledger_transaction_id'] = $entry['raw_data']['ledger_transaction_id'];
                        if (filled($entry['whatnot_order_id']) && ! filled($existing->whatnot_order_id)) {
                            $existing->whatnot_order_id = $entry['whatnot_order_id'];
                        }
                        if (! empty($entry['raw_data']['livestream_id']) && empty($raw['livestream_id'])) {
                            $raw['livestream_id'] = $entry['raw_data']['livestream_id'];
                        }
                        $raw['_seller_api_report'] = $entry['raw_data'];
                        $existing->forceFill(['raw_data' => $raw])->save();
                        if ($matcher->match($existing) === 'matched') {
                            $counts['matched_to_show']++;
                            if ($isTip) $counts['tips_attributed']++;
                        }
                    }
                    continue;
                }

                $completed = Carbon::parse($entry['completed_date']);
                if ($backfillFrom !== null && $completed->gte($backfillFrom) && ($start === null || $completed->lt($start))) {
                    // Backfill: a day the scraper has no ledger rows for is
                    // filled in; on a day it covered, an unmatched API row
                    // may be a near-duplicate, so it is left for review.
                    $day = $completed->toDateString();
                    $coveredDays[$day] ??= WhatnotLedgerEntry::query()->where('whatnot_channel_id', $channelId)
                        ->where(fn ($q) => $q->whereNull('dedup_key')->orWhere('dedup_key', 'not like', 'wn_api:%'))
                        ->where(fn ($q) => $q->whereDate('completed_date', $day)->orWhere(fn ($q) => $q->whereNull('completed_date')->whereDate('created_date', $day)))
                        ->exists();
                    if ($coveredDays[$day]) {
                        $counts['covered_day_unmatched']++;
                        continue;
                    }
                } elseif ($start === null || $completed->lt($start)) {
                    $counts['before_start']++;
                    if ($isTip) $counts['tips_before_start']++;
                    continue;
                }

                $counts['created']++;
                if ($isTip) $counts['tips_created']++;
                if (! $dryRun) {
                    // Tie it to its show and streamer now, not at the next hourly match.
                    if ($matcher->match(WhatnotLedgerEntry::create($entry)) === 'matched') {
                        $counts['matched_to_show']++;
                        if ($isTip) $counts['tips_attributed']++;
                    }
                }
            }
        };
        $dryRun ? $run() : DB::transaction($run);

        return ['counts' => $counts, 'dry_run' => $dryRun];
    }

    /** First day API ledger rows are created; null = not chosen yet (nothing is created). */
    public function ledgerStart(): ?Carbon
    {
        $value = config('services.whatnot_seller.ledger_start');
        if (! is_string($value) || trim($value) === '') return null;
        try {
            return Carbon::parse($value, config('app.timezone'))->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return list<array<string,string|null>> */
    public function parse(string $csv): array
    {
        try {
            return WhatnotApiCsv::rows($csv, self::REQUIRED_COLUMNS);
        } catch (RuntimeException $e) {
            throw new RuntimeException('Unexpected financial report format: '.$e->getMessage());
        }
    }

    /** @param array<string,string|null> $row */
    private function toEntry(int $channelId, array $row): ?array
    {
        $id = trim((string) ($row['ledger_transaction_id'] ?? ''));
        $amount = $row['transaction_amount'] ?? null;
        $type = strtoupper(trim((string) ($row['transaction_type'] ?? '')));
        if ($id === '' || $type === '' || ! is_numeric($amount) || ($row['transaction_currency'] ?? null) !== 'USD') {
            return null;
        }
        try {
            $completed = Carbon::parse((string) $row['transaction_completed_at_utc'], 'UTC')->setTimezone(config('app.timezone'));
            $placed = filled($row['order_placed_at_utc'] ?? null)
                ? Carbon::parse((string) $row['order_placed_at_utc'], 'UTC')->setTimezone(config('app.timezone'))
                : $completed;
        } catch (\Throwable) {
            return null;
        }

        $raw = array_diff_key($row, array_flip(self::DROP));
        $raw['_source'] = 'seller_api_report';
        if (filled($row['livestream_id'] ?? null)) $raw['livestream_id'] = strtolower((string) $row['livestream_id']);

        return [
            'whatnot_channel_id' => $channelId,
            'created_date' => $placed->toDateTimeString(),
            'completed_date' => $completed->toDateTimeString(),
            'amount' => round((float) $amount, 2),
            'listing_id' => null,
            'whatnot_order_id' => filled($row['order_id'] ?? null) ? (string) $row['order_id'] : null,
            'order_hash' => null,
            'message' => $row['transaction_message'] ?? null,
            'status' => 'Completed',
            'transaction_type' => $type === 'TIP' ? 'Tip' : ucfirst(strtolower(str_replace('_', ' ', $type))),
            'dedup_key' => 'wn_api:'.$id,
            'raw_data' => $raw,
        ];
    }

    /**
     * Carrier adjustments carry no show (Whatnot's docs), but they do carry the
     * shipment: shipment → its orders → their show. Only when that resolves to
     * exactly one show is the show recorded, as livestream_id for the matcher.
     */
    private function showViaShipment(int $channelId, array &$entry): bool
    {
        $raw = $entry['raw_data'];
        $shipmentId = $raw['shipment_id'] ?? null;
        if (! empty($raw['livestream_id']) || ! is_string($shipmentId) || $shipmentId === '') {
            return false;
        }
        $shows = DB::table('whatnot_show_orders as o')->join('shows as s', 's.id', '=', 'o.show_id')
            ->where('o.whatnot_shipment_id', $shipmentId)->where('s.whatnot_channel_id', $channelId)
            ->whereNotNull('s.whatnot_show_id')->distinct()->limit(2)->pluck('s.whatnot_show_id');
        if ($shows->count() !== 1) {
            return false;
        }
        $entry['raw_data']['livestream_id'] = strtolower((string) $shows->first());
        $entry['raw_data']['_livestream_via'] = 'shipment';

        return true;
    }

    /** Scraper rows that are this same transaction. */
    private function scraperMatches(int $channelId, array $entry)
    {
        $base = WhatnotLedgerEntry::query()
            ->where('whatnot_channel_id', $channelId)
            ->where(fn ($q) => $q->whereNull('dedup_key')->orWhere('dedup_key', 'not like', 'wn_api:%'))
            ->whereRaw('ABS(amount - ?) < 0.005', [$entry['amount']]);

        $isTip = $entry['transaction_type'] === 'Tip';
        if ($entry['whatnot_order_id'] !== null && ! $isTip) {
            return (clone $base)->where('whatnot_order_id', $entry['whatnot_order_id'])->limit(2)->get();
        }

        $at = Carbon::parse($entry['completed_date']);
        $from = $at->copy()->subMinutes(self::TIP_MATCH_MINUTES)->toDateTimeString();
        $to = $at->copy()->addMinutes(self::TIP_MATCH_MINUTES)->toDateTimeString();

        return (clone $base)
            ->when($isTip, fn ($q) => $q->whereRaw('LOWER(TRIM(transaction_type)) IN (?, ?)', ['tip', 'tips']))
            ->where(fn ($q) => $q->whereBetween('completed_date', [$from, $to])->orWhereBetween('created_date', [$from, $to]))
            ->limit(2)->get();
    }
}
