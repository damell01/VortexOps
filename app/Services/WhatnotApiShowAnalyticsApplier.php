<?php

namespace App\Services;

use App\Models\Show;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Apply GET /analytics/shows/{show_id} (ShowAnalytics) to the show's figures.
 *
 * Fields map to the same columns the scraper fills from Seller Hub analytics.
 * orders_count is not written to units_sold: Whatnot counts orders, Seller
 * Hub's "sold" counts items. Only GENERATED analytics are applied; the
 * scraper's own protections are kept — a locked show (pending approval,
 * reconciled, closed) is flagged as revised with a note, verified tips are
 * never touched (analytics carry no tips), and changes are tracked.
 */
final class WhatnotApiShowAnalyticsApplier
{
    /** Cache schema metadata for the lifetime of each sync process. */
    private static ?array $showColumns = null;

    private static function hasShowColumn(string $column): bool
    {
        self::$showColumns ??= array_fill_keys(Schema::getColumnListing('shows'), true);

        return isset(self::$showColumns[$column]);
    }

    /** ShowAnalytics field => shows column. */
    private const MONEY = [
        'sales' => 'gross_revenue',
        'estimated_earnings' => 'whatnot_net',
        'average_order_value' => 'avg_order_value',
        'giveaway_spend' => 'giveaway_spend',
    ];

    private const COUNTS = [
        'stream_minutes' => 'show_duration',
        'giveaway_orders_count' => 'giveaways_count',
        'buyers_count' => 'buyers_count',
        'first_time_buyers_count' => 'first_time_buyers',
        'returning_buyers_count' => 'returning_buyers',
        'buyer_shares_count' => 'shares_count',
        'max_concurrent_viewers' => 'max_concurrent_viewers',
        'total_views' => 'total_views',
    ];

    private const LOCKED = ['pending_approval', 'reconciled', 'closed'];

    /**
     * @param array<string,mixed> $analytics
     * @return array{outcome:string, show_id:?int, changes:array<string,mixed>}
     */
    public function apply(int $channelId, array $analytics, bool $dryRun = true): array
    {
        if (! $dryRun && ! WhatnotApiOrderApplier::writesAllowed()) {
            throw new RuntimeException('Applying API data is disabled.');
        }

        $whatnotShowId = is_string($analytics['show_id'] ?? null) ? strtolower($analytics['show_id']) : null;
        if (! $whatnotShowId || ! preg_match('/^[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}$/', $whatnotShowId)) {
            return ['outcome' => 'invalid', 'show_id' => null, 'changes' => []];
        }

        $shows = Show::query()->where('whatnot_channel_id', $channelId)
            ->whereRaw('LOWER(whatnot_show_id) = ?', [$whatnotShowId])->limit(2)->get();
        if ($shows->count() !== 1) {
            return ['outcome' => $shows->isEmpty() ? 'show_not_found' : 'show_ambiguous', 'show_id' => null, 'changes' => []];
        }
        $show = $shows->first();
        $status = $analytics['calculation_status'] ?? null;

        if ($status === 'WILL_NOT_BE_CALCULATED') {
            // Cancelled, private or a rehearsal: nothing will ever arrive.
            $mark = array_filter([
                'analytics_sync_status' => self::hasShowColumn('analytics_sync_status') ? 'unavailable' : null,
                'analytics_unavailable_at' => self::hasShowColumn('analytics_unavailable_at') && ! $show->analytics_unavailable_at ? now() : null,
            ]);
            if (! $dryRun && $mark) $show->forceFill($mark)->save();
            return ['outcome' => 'not_calculated', 'show_id' => $show->id, 'changes' => []];
        }
        if ($status !== 'GENERATED') {
            return ['outcome' => 'not_ready', 'show_id' => $show->id, 'changes' => []]; // NOT_ENDED / PENDING
        }

        $values = [];
        foreach (self::MONEY as $field => $column) {
            $money = $analytics[$field] ?? null;
            if (is_array($money) && ($money['currency'] ?? null) === 'USD' && is_string($money['amount'] ?? null)
                && preg_match('/^-?\d{1,12}(?:\.\d{1,4})?$/', $money['amount'])) {
                $values[$column] = round((float) $money['amount'], 2);
            }
        }
        foreach (self::COUNTS as $field => $column) {
            if (is_int($analytics[$field] ?? null)) $values[$column] = $analytics[$field];
        }
        if (is_numeric($analytics['average_rating'] ?? null)) {
            $values['avg_order_rating'] = round((float) $analytics['average_rating'], 2);
        }
        $values = array_filter($values, fn ($v, $column) => self::hasShowColumn($column), ARRAY_FILTER_USE_BOTH);

        $changes = array_filter($values, function ($value, $column) use ($show) {
            $current = $show->{$column};
            return is_numeric($current) ? abs((float) $current - (float) $value) > 0.0001 : $current === null;
        }, ARRAY_FILTER_USE_BOTH);

        $stamp = array_filter([
            'analytics_sync_status' => self::hasShowColumn('analytics_sync_status') ? 'complete' : null,
            'last_analytics_synced_at' => self::hasShowColumn('last_analytics_synced_at') ? now() : null,
        ]);

        if ($changes !== [] && in_array($show->status, self::LOCKED, true)) {
            $notes = [];
            foreach (['gross_revenue', 'whatnot_net'] as $field) {
                if (array_key_exists($field, $changes) && abs((float) $show->{$field} - (float) $changes[$field]) > 0.01) {
                    $notes[] = "{$field}: ".(float) $show->{$field}.' → '.(float) $changes[$field];
                }
            }
            if ($notes) {
                $changes['financials_revised_after_lock'] = true;
                $changes['revision_notes'] = trim(($show->revision_notes ? $show->revision_notes."\n" : '').now()->format('M j, Y g:ia').' — Seller API: '.implode(', ', $notes));
            }
        }

        if (! $dryRun) {
            if ($changes !== []) WhatnotApiShowImporter::logChanges($show, array_diff_key($changes, ['revision_notes' => true]), 'Seller API show analytics');
            $show->forceFill($changes + $stamp)->save();
        }

        return ['outcome' => $changes === [] ? 'unchanged' : 'updated', 'show_id' => $show->id, 'changes' => $changes];
    }
}
