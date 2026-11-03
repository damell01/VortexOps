<?php

namespace App\Services;

use App\Models\Show;
use App\Models\WhatnotApiConnection;
use App\Models\WhatnotApiSyncAudit;
use Illuminate\Support\Carbon;
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
    private const REPORT_POLLS = 12;
    private const REPORT_POLL_SECONDS = 5;

    public function __construct(
        private readonly WhatnotSellerApiClient $client,
        private readonly WhatnotApiReadOnlySync $snapshots,
        private readonly WhatnotApiOrderApplier $orders,
        private readonly WhatnotApiShowAnalyticsApplier $analytics,
        private readonly WhatnotApiLedgerImporter $ledger,
    ) {}

    /**
     * @param callable(string):void|null $log
     * @return array<string,mixed>
     */
    public function run(WhatnotApiConnection $connection, int $days = 3, bool $dryRun = true, ?callable $log = null): array
    {
        $log ??= fn () => null;
        $channelId = (int) $connection->whatnot_channel_id;
        $since = now()->subDays(max(1, $days));
        $summary = ['channel_id' => $channelId, 'days' => $days, 'dry_run' => $dryRun, 'errors' => []];

        // 1. Orders, newest first, until older than the window.
        $orderCounts = [];
        $needsItems = [];
        try {
            $paging = $this->client->eachPage($connection, 'orders', function (array $page) use ($channelId, $dryRun, $since, &$orderCounts, &$needsItems) {
                $snapshot = $this->snapshots->store($channelId, 'orders', $page);
                $result = $this->orders->apply($channelId, $snapshot['id'], $dryRun);
                foreach ($result['counts'] as $k => $n) $orderCounts[$k] = ($orderCounts[$k] ?? 0) + $n;
                array_push($needsItems, ...$result['needs_items']);

                return ! $this->pageIsOlderThan($page['data'], 'created_at', $since);
            });
            if (! $paging['ok']) $summary['errors'][] = 'orders: HTTP '.$paging['http_status'];
            $summary['orders'] = $orderCounts + ['pages' => $paging['pages']];
            $log('Orders: '.json_encode($summary['orders']));
        } catch (\Throwable $e) {
            $summary['errors'][] = 'orders: '.$e->getMessage();
        }

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
        $log('Order items: '.json_encode($summary['order_items']));

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

            $known = Show::query()->where('whatnot_channel_id', $channelId)
                ->whereIn(\Illuminate\Support\Facades\DB::raw('LOWER(whatnot_show_id)'), $showIds ?: ['-'])
                ->pluck('whatnot_show_id')->map(fn ($id) => strtolower($id))->unique()->values();
            $analyticsCounts['not_in_vortexops'] = count(array_unique($showIds)) - $known->count();
            foreach ($known->take(self::MAX_ANALYTICS_LOOKUPS) as $id) {
                $response = $this->client->call($connection, 'show_analytics', $id);
                if (! $response->successful()) {
                    $analyticsCounts['failed'] = ($analyticsCounts['failed'] ?? 0) + 1;
                    continue;
                }
                $outcome = $this->analytics->apply($channelId, (array) $response->json(), $dryRun)['outcome'];
                $analyticsCounts[$outcome] = ($analyticsCounts[$outcome] ?? 0) + 1;
            }
            $summary['show_analytics'] = $analyticsCounts;
            $log('Show analytics: '.json_encode($analyticsCounts));
        } catch (\Throwable $e) {
            $summary['errors'][] = 'show analytics: '.$e->getMessage();
        }

        // 4. Financial report → ledger (tips, earnings, refunds).
        try {
            $summary['ledger'] = $this->ledgerStep($connection, $since, $dryRun);
            $log('Ledger: '.json_encode($summary['ledger']));
        } catch (\Throwable $e) {
            $summary['errors'][] = 'ledger: '.$e->getMessage();
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
        $end = now()->utc()->startOfHour();
        $start = $since->copy()->utc();
        $response = $this->client->call($connection, 'generate_report', body: ['report_parameters' => [
            'report_type' => 'seller_financial_report',
            'start_date' => $start->format('Y-m-d\TH:i:s.v\Z'),
            'end_date' => $end->format('Y-m-d\TH:i:s.v\Z'),
        ]]);
        if (! $response->successful()) {
            return ['status' => 'request_failed', 'http_status' => $response->status()];
        }
        $report = (array) $response->json();
        for ($i = 0; ($report['status'] ?? null) !== 'completed' && $i < self::REPORT_POLLS; $i++) {
            if (in_array($report['status'] ?? null, ['failed', 'cancelled'], true)) {
                return ['status' => 'report_'.$report['status']];
            }
            $this->client->pause(self::REPORT_POLL_SECONDS);
            $poll = $this->client->call($connection, 'report', (string) ($report['id'] ?? ''));
            if (! $poll->successful()) return ['status' => 'poll_failed', 'http_status' => $poll->status()];
            $report = (array) $poll->json();
        }
        if (($report['status'] ?? null) !== 'completed' || ! is_string($report['report_url'] ?? null)) {
            // Whatnot returns this same run for an identical request, so the next sync picks it up.
            return ['status' => 'still_generating', 'report_id' => $report['id'] ?? null];
        }

        $import = $this->ledger->import((int) $connection->whatnot_channel_id, $this->client->downloadReport($report['report_url']), $dryRun);

        return ['status' => 'imported', 'report_id' => $report['id'] ?? null] + $import['counts'];
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
