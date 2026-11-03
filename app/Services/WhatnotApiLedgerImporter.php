<?php

namespace App\Services;

use App\Models\WhatnotLedgerEntry;
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
            'ambiguous' => 0, 'before_start' => 0, 'covered_day_unmatched' => 0, 'invalid' => 0, 'tips' => 0];
        $coveredDays = [];

        $run = function () use ($rows, $channelId, $dryRun, $start, $backfillFrom, &$counts, &$coveredDays) {
            foreach ($rows as $row) {
                $entry = $this->toEntry($channelId, $row);
                if ($entry === null) {
                    $counts['invalid']++;
                    continue;
                }
                if (strcasecmp($entry['transaction_type'], 'Tip') === 0) $counts['tips']++;

                if (WhatnotLedgerEntry::where('dedup_key', $entry['dedup_key'])->exists()) {
                    $counts['unchanged']++;
                    continue;
                }

                $candidates = $this->scraperMatches($channelId, $entry);
                if ($candidates->count() > 1) {
                    $counts['ambiguous']++;
                    continue;
                }
                if ($candidates->count() === 1) {
                    $counts['linked']++;
                    if (! $dryRun) {
                        $existing = $candidates->first();
                        $raw = is_array($existing->raw_data) ? $existing->raw_data : [];
                        $raw['ledger_transaction_id'] = $entry['raw_data']['ledger_transaction_id'];
                        if (! empty($entry['raw_data']['livestream_id']) && empty($raw['livestream_id'])) {
                            $raw['livestream_id'] = $entry['raw_data']['livestream_id'];
                        }
                        $raw['_seller_api_report'] = $entry['raw_data'];
                        $existing->forceFill(['raw_data' => $raw])->save();
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
                    continue;
                }

                $counts['created']++;
                if (! $dryRun) {
                    WhatnotLedgerEntry::create($entry);
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
