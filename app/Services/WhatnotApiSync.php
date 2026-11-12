<?php

namespace App\Services;

use App\Models\Show;
use App\Models\WhatnotApiConnection;
use App\Models\WhatnotApiSyncAudit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * One Seller API sync for a channel: orders → line items → show analytics →
 * financial report (ledger, tips). Reads are always live; writes follow the
 * appliers' rules and happen only when $dryRun is false and writes are
 * allowed (WhatnotApiOrderApplier::writesAllowed()).
 *
 * Endpoints and limits per developers.whatnot.com: lists page with
 * limit ≤ 50 and next_cursor, newest first; /orders cannot be filtered, so
 * paging stops once a page is older than the look-back window.
 */
final class WhatnotApiSync
{
    public const MAX_ITEM_LOOKUPS = 150;
    public const MAX_ANALYTICS_LOOKUPS = 60;

    public function __construct(
        private readonly WhatnotSellerApiClient $client,
        private readonly WhatnotApiReadOnlySync $snapshots,
        private readonly WhatnotApiOrderApplier $orders,
        private readonly WhatnotApiShowAnalyticsApplier $analytics,
        private readonly WhatnotApiLedgerImporter $ledger,
        private readonly WhatnotApiShowImporter $shows,
        private readonly WhatnotApiShipmentImporter $shipments,
        private readonly WhatnotApiOverviewImporter $overview,
        private readonly WhatnotApiReports $reports,
    ) {}

    /**
     * @param callable(string):void|null $log
     * @return array<string,mixed>
     */
    public function run(WhatnotApiConnection $connection, int $days = 3, bool $dryRun = true, ?callable $log = null, ?array $only = null): array
    {
        $log ??= fn () => null;
        $channelId = (int) $connection->whatnot_channel_id;
        $since = now()->subDays(max(1, $days));
        $summary = ['channel_id' => $channelId, 'days' => $days, 'dry_run' => $dryRun, 'errors' => []];

        if ($only === null || in_array('shows', $only, true)) {
        // 0. Shows: every scheduled show (created ahead of time; reschedules
        // and title changes logged), and shows that ended in the window.
        $showCounts = [];
        try {
            foreach (['scheduled' => false, 'ended' => true] as $status => $windowed) {
                $paging = $this->client->eachPage($connection, 'shows', function (array $page) use ($channelId, $dryRun, $since, $windowed, &$showCounts) {
                    foreach ($page['data'] as $show) {
                        if (! is_array($show)) continue;
                        $outcome = $this->shows->import($channelId, $show, $dryRun)['outcome'];
                        $showCounts[$outcome] = ($showCounts[$outcome] ?? 0) + 1;
                    }
                    return ! $windowed || ! $this->pageIsOlderThan($page['data'], 'scheduled_start_at', $since);
                }, ['status' => $status], maxPages: 20);
                if (! $paging['ok']) $summary['errors'][] = 'shows ('.$status.'): HTTP '.$paging['http_status'];
            }
            $summary['shows'] = $showCounts;
            $log('Shows: '.json_encode($showCounts));
        } catch (\Throwable $e) {
            $summary['errors'][] = 'shows: '.$e->getMessage();
        }

        }

        $needsItems = [];
        if ($only === null || in_array('orders', $only, true)) {
        // 1. Orders, newest first, until older than the window.
        $orderCounts = [];
        $cursorKey = 'whatnot-api-orders-cursor:'.$channelId;
        $startCursor = Cache::get($cursorKey);
        if (! is_string($startCursor)) $startCursor = null;
        try {
            $paging = $this->client->eachPage($connection, 'orders', function (array $page) use ($channelId, $dryRun, $since, &$orderCounts, &$needsItems) {
                $snapshot = $this->snapshots->store($channelId, 'orders', $page);
                $result = $this->orders->apply($channelId, $snapshot['id'], $dryRun);
                foreach ($result['counts'] as $k => $n) $orderCounts[$k] = ($orderCounts[$k] ?? 0) + $n;
                array_push($needsItems, ...$result['needs_items']);

                return ! $this->pageIsOlderThan($page['data'], 'created_at', $since);
            }, maxPages: 50, startCursor: $startCursor, allowIncomplete: true);
            if ($paging['ok']) {
                if (! empty($paging['incomplete']) && is_string($paging['next_cursor'] ?? null)) {
                    Cache::put($cursorKey, $paging['next_cursor'], now()->addHours(12));
                } else {
                    Cache::forget($cursorKey);
                }
            }
            if (! $paging['ok']) $summary['errors'][] = 'orders: HTTP '.$paging['http_status'];
            $summary['orders'] = $orderCounts + ['pages' => $paging['pages'], 'incomplete' => (bool) ($paging['incomplete'] ?? false)];
            $log('Orders: '.json_encode($summary['orders']));
        } catch (\Throwable $e) {
            $summary['errors'][] = 'orders: '.$e->getMessage();
        }

        }

        if ($only === null || in_array('items', $only, true)) {
        // 2. Line items for orders still missing an item name.
        $items = ['filled' => 0, 'unchanged' => 0, 'no_row' => 0, 'no_items' => 0, 'failed' => 0];
        foreach (array_slice(array_values(array_unique($needsItems)), 0, self::MAX_ITEM_LOOKUPS) as $orderId) {
            try {
                $response = $this->client->call($connection, 'order_items', (string) $orderId, ['limit' => WhatnotSellerApiClient::MAX_PAGE_SIZE]);
                if (! $response->successful()) { $items['failed']++; continue; }
                $items[$this->orders->applyItems($channelId, (string) $orderId, (array) ($response->json('data') ?? []), $dryRun)]++;
            } catch (\Throwable $e) {
                $items['failed']++;
            }
        }
        $summary['order_items'] = $items + ['waiting' => max(0, count(array_unique($needsItems)) - self::MAX_ITEM_LOOKUPS)];
        if ($items['failed'] > 0) $summary['errors'][] = 'order items: '.$items['failed'].' lookups failed';
        if ($summary['order_items']['waiting'] > 0) $summary['errors'][] = 'order items: '.$summary['order_items']['waiting'].' lookups deferred to another sync';
        $log('Order items: '.json_encode($summary['order_items']));

        }

        if ($only === null || in_array('shipments', $only, true)) {
        // 2b. Shipments created in the window → tracking/status on orders.
        $shipCounts = [];
        try {
            $paging = $this->client->eachPage($connection, 'shipments', function (array $page) use ($connection, $channelId, $dryRun, $since, &$shipCounts) {
                foreach ($page['data'] as $shipment) {
                    if (! is_array($shipment) || ! is_string($shipment['id'] ?? null)) continue;
                    $items = $this->client->call($connection, 'shipment_items', $shipment['id'], ['limit' => WhatnotSellerApiClient::MAX_PAGE_SIZE]);
                    $outcome = $items->successful()
                        ? $this->shipments->import($channelId, $shipment, (array) ($items->json('data') ?? []), $dryRun)['outcome']
                        : 'http_'.$items->status();
                    $shipCounts[$outcome] = ($shipCounts[$outcome] ?? 0) + 1;
                }
                return ! $this->pageIsOlderThan($page['data'], 'created_at', $since);
            }, maxPages: 40);
            if (! $paging['ok']) $summary['errors'][] = 'shipments: HTTP '.$paging['http_status'];
            if (collect($shipCounts)->keys()->contains(fn ($key) => str_starts_with((string) $key, 'http_'))) $summary['errors'][] = 'shipments: one or more shipment item requests failed';
            $summary['shipments'] = $shipCounts;
            $log('Shipments: '.json_encode($shipCounts));
        } catch (\Throwable $e) {
            $summary['errors'][] = 'shipments: '.$e->getMessage();
        }

        }

        if ($only === null || in_array('analytics', $only, true)) {
        // 3. Ended shows in the window that exist here → their analytics.
        $analyticsCounts = [];
        try {
            $showIds = [];
            $paging = $this->client->eachPage($connection, 'shows', function (array $page) use ($since, &$showIds) {
                foreach ($page['data'] as $show) {
                    if (is_array($show) && is_string($show['id'] ?? null)) $showIds[] = strtolower($show['id']);
                }
                return ! $this->pageIsOlderThan($page['data'], 'scheduled_start_at', $since);
            }, ['status' => 'ended']);
            if (! $paging['ok']) $summary['errors'][] = 'shows: HTTP '.$paging['http_status'];

            // Prioritize recently ended shows without complete analytics. The API's
            // returned order is not a reliable priority for our per-run lookup cap.
            $known = Show::query()->where('whatnot_channel_id', $channelId)
                ->whereIn(\Illuminate\Support\Facades\DB::raw('LOWER(whatnot_show_id)'), $showIds ?: ['-'])
                ->orderByRaw("CASE WHEN analytics_sync_status = 'complete' THEN 1 ELSE 0 END")
                ->orderByDesc('show_date')
                ->orderByDesc('start_time')
                ->pluck('whatnot_show_id')->map(fn ($id) => strtolower($id))->unique()->values();
            $analyticsCounts['not_in_vortexops'] = count(array_unique($showIds)) - $known->count();
            foreach ($known->take(self::MAX_ANALYTICS_LOOKUPS) as $id) {
                $showLabel = Show::query()->where('whatnot_channel_id', $channelId)
                    ->whereRaw('LOWER(whatnot_show_id) = ?', [$id])->value('title') ?: $id;
                $log('Analytics: '.$showLabel.' — fetching');
                $response = $this->client->call($connection, 'show_analytics', $id);
                if (! $response->successful()) {
                    $analyticsCounts['failed'] = ($analyticsCounts['failed'] ?? 0) + 1;
                    $log('Analytics: '.$showLabel.' — HTTP '.$response->status());
                    continue;
                }
                $outcome = $this->analytics->apply($channelId, (array) $response->json(), $dryRun)['outcome'];
                $log('Analytics: '.$showLabel.' — '.$outcome);
                $analyticsCounts[$outcome] = ($analyticsCounts[$outcome] ?? 0) + 1;
            }
            if (($analyticsCounts['failed'] ?? 0) > 0) $summary['errors'][] = 'show analytics: '.$analyticsCounts['failed'].' requests failed';
            if ($known->count() > self::MAX_ANALYTICS_LOOKUPS) $summary['errors'][] = 'show analytics: '.($known->count() - self::MAX_ANALYTICS_LOOKUPS).' lookups deferred';
            $summary['show_analytics'] = $analyticsCounts;
            $log('Show analytics: '.json_encode($analyticsCounts));
        } catch (\Throwable $e) {
            $summary['errors'][] = 'show analytics: '.$e->getMessage();
        }

        }

        if ($only === null || in_array('ledger', $only, true)) {
        // 4. Financial report → ledger (tips, earnings, refunds).
        try {
            $summary['ledger'] = $this->ledgerStep($connection, $since, $dryRun);
            if (($summary['ledger']['status'] ?? null) !== 'imported') $summary['errors'][] = 'ledger: financial report not yet imported';
            if (isset($summary['ledger']['received'], $summary['ledger']['accounted_for']) && (int) $summary['ledger']['received'] !== (int) $summary['ledger']['accounted_for']) $summary['errors'][] = 'ledger: row counts do not reconcile';
            if (($summary['ledger']['ambiguous'] ?? 0) > 0) $summary['errors'][] = 'ledger: ambiguous duplicate candidates require manual review';
            if (($summary['ledger']['invalid'] ?? 0) > 0) $summary['errors'][] = 'ledger: invalid source rows require manual review';
            $log('Ledger: '.json_encode($summary['ledger']));
        } catch (\Throwable $e) {
            $summary['errors'][] = 'ledger: '.$e->getMessage();
        }

        }

        if ($only === null || in_array('overview', $only, true)) {
        // 5. Daily business overview for the window.
        try {
            $report = $this->reports->fetch($connection, WhatnotApiReports::overview($since->copy()->startOfDay(), now()->startOfHour()));
            $summary['overview'] = $report['status'] === 'completed'
                ? $this->overview->import($channelId, $report['csv'], $dryRun)
                : ['status' => $report['status']];
            if ($report['status'] !== 'completed') $summary['errors'][] = 'overview: report '.$report['status'];
        } catch (\Throwable $e) {
            $summary['errors'][] = 'overview: '.$e->getMessage();
        }

        }

        WhatnotApiSyncAudit::create([
            'whatnot_channel_id' => $channelId,
            'resource' => 'sync',
            'status' => $summary['errors'] ? 'partial' : ($dryRun ? 'dry_run' : 'applied'),
            'summary' => $summary,
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        return $summary;
    }

    /** @return array<string,mixed> */
    private function ledgerStep(WhatnotApiConnection $connection, Carbon $since, bool $dryRun): array
    {
        $report = $this->reports->fetch($connection, WhatnotApiReports::financial($since, now()->startOfHour()));
        if ($report['status'] !== 'completed') {
            // Whatnot returns the same run for an identical request, so the next sync picks it up.
            return array_diff_key($report, ['csv' => true]);
        }
        $import = $this->ledger->import((int) $connection->whatnot_channel_id, $report['csv'], $dryRun);
        $sourceNet = array_reduce($this->ledger->parse($report['csv']), fn ($sum, $row) => $sum + (is_numeric($row['transaction_amount'] ?? null) ? (int) round((float) $row['transaction_amount'] * 100) : 0), 0);

        return ['status' => 'imported', 'report_id' => $report['report_id'] ?? null, 'source_net_cents' => $sourceNet, 'accounted_for' => array_sum(array_map(fn ($key) => (int) ($import['counts'][$key] ?? 0), ['created', 'linked', 'unchanged', 'ambiguous', 'before_start', 'covered_day_unmatched', 'invalid']))] + $import['counts'];
    }

    /** True when the page reaches back past $since (pages are newest first), so no further page is needed. */
    private function pageIsOlderThan(array $rows, string $field, Carbon $since): bool
    {
        $dates = array_filter(array_map(fn ($r) => is_array($r) && is_string($r[$field] ?? null) ? $r[$field] : null, $rows));
        if ($dates === []) return false;
        try {
            return Carbon::parse(min($dates))->lt($since);
        } catch (\Throwable) {
            throw new RuntimeException("Unexpected {$field} value in a Whatnot list page.");
        }
    }
}
