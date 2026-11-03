<?php

namespace App\Services;

use App\Models\Show;
use App\Models\WhatnotApiConnection;
use App\Models\WhatnotApiSyncAudit;
use App\Models\WhatnotLedgerEntry;
use App\Models\WhatnotShowOrder;
use Illuminate\Support\Carbon;

/**
 * Read-only check that the Seller API and VortexOps agree, before turning on
 * WHATNOT_SELLER_APPLY_ENABLED. Nothing is written except the audit row.
 *
 * For recent ended shows that exist here: API analytics gross vs the show's
 * gross, API order count vs the show's orders, and how many order IDs match
 * (matching IDs are what keeps the apply step from double counting). For the
 * window: financial-report tips vs ledger tips.
 */
final class WhatnotApiVerifier
{
    private const GROSS_TOLERANCE = 1.00;
    private const MAX_SHOWS = 25;

    public function __construct(
        private readonly WhatnotSellerApiClient $client,
        private readonly WhatnotApiHealth $health,
        private readonly WhatnotApiLedgerImporter $ledger,
    ) {}

    /** @return array<string,mixed> */
    public function verify(WhatnotApiConnection $connection, int $days = 7): array
    {
        $channelId = (int) $connection->whatnot_channel_id;
        $since = now()->subDays(max(1, $days));
        $report = ['channel_id' => $channelId, 'days' => $days, 'problems' => [], 'shows' => []];

        $probe = $this->health->probe($connection);
        $report['connection'] = $probe['checks'];
        if (! $probe['ok']) {
            $report['problems'][] = $probe['message'];
            return $this->finish($report);
        }

        // API orders in the window, grouped by show.
        $apiOrders = [];
        $this->client->eachPage($connection, 'orders', function (array $page) use ($since, &$apiOrders) {
            $oldest = null;
            foreach ($page['data'] as $order) {
                if (! is_array($order)) continue;
                $created = is_string($order['created_at'] ?? null) ? $order['created_at'] : null;
                $oldest = $created === null ? $oldest : min($oldest ?? $created, $created);
                $showId = is_string($order['show_id'] ?? null) ? strtolower($order['show_id']) : null;
                if ($showId === null || in_array($order['status'] ?? null, WhatnotApiOrderApplier::NOT_A_SALE, true)) continue;
                $apiOrders[$showId][] = (string) ($order['id'] ?? '');
            }
            return $oldest === null || Carbon::parse($oldest)->gte($since);
        });

        // Ended shows in the window that exist in VortexOps.
        $apiShowIds = [];
        $this->client->eachPage($connection, 'shows', function (array $page) use ($since, &$apiShowIds) {
            $oldest = null;
            foreach ($page['data'] as $show) {
                if (! is_array($show) || ! is_string($show['id'] ?? null)) continue;
                $apiShowIds[] = strtolower($show['id']);
                if (is_string($show['scheduled_start_at'] ?? null)) $oldest = min($oldest ?? $show['scheduled_start_at'], $show['scheduled_start_at']);
            }
            return $oldest === null || Carbon::parse($oldest)->gte($since);
        }, ['status' => 'ended']);

        $local = Show::query()->where('whatnot_channel_id', $channelId)
            ->whereIn(\Illuminate\Support\Facades\DB::raw('LOWER(whatnot_show_id)'), $apiShowIds ?: ['-'])
            ->orderByDesc('show_date')->limit(self::MAX_SHOWS)->get();
        $report['shows_not_in_vortexops'] = count(array_unique($apiShowIds)) - $local->count();

        foreach ($local as $show) {
            $uuid = strtolower((string) $show->whatnot_show_id);
            $analytics = $this->client->call($connection, 'show_analytics', $uuid);
            $apiGross = null;
            if ($analytics->successful() && $analytics->json('calculation_status') === 'GENERATED'
                && $analytics->json('sales.currency') === 'USD') {
                $apiGross = round((float) $analytics->json('sales.amount'), 2);
            }
            $ids = array_values(array_filter($apiOrders[$uuid] ?? []));
            $localIds = WhatnotShowOrder::query()->where('show_id', $show->id)->whereNotNull('whatnot_order_id')
                ->pluck('whatnot_order_id')->map(fn ($id) => (string) $id)->all();
            $matched = count(array_intersect($ids, $localIds));

            $row = [
                'show' => $show->title, 'date' => (string) $show->show_date?->toDateString(),
                'api_gross' => $apiGross, 'vortex_gross' => $show->gross_revenue !== null ? round((float) $show->gross_revenue, 2) : null,
                'api_orders' => count($ids), 'vortex_orders' => count($localIds), 'matching_order_ids' => $matched,
                'status' => 'ok',
            ];
            if ($apiGross !== null && $row['vortex_gross'] !== null && abs($apiGross - $row['vortex_gross']) > self::GROSS_TOLERANCE) {
                $row['status'] = 'gross differs';
            }
            if ($ids !== [] && $localIds !== [] && $matched === 0) {
                $row['status'] = 'order IDs do not match';
                $report['problems'][] = "{$show->title}: none of the API's order IDs match the scraper's — applying would skip this show.";
            }
            $report['shows'][] = $row;
        }

        $report['tips'] = $this->compareTips($connection, $since);

        return $this->finish($report);
    }

    /** @return array<string,mixed> */
    private function compareTips(WhatnotApiConnection $connection, Carbon $since): array
    {
        $end = now()->utc()->startOfHour();
        $response = $this->client->call($connection, 'generate_report', body: ['report_parameters' => [
            'report_type' => 'seller_financial_report',
            'start_date' => $since->copy()->utc()->format('Y-m-d\TH:i:s.v\Z'),
            'end_date' => $end->format('Y-m-d\TH:i:s.v\Z'),
        ]]);
        if ($response->status() === 403) {
            return ['status' => 'needs write:reports to generate the financial report'];
        }
        if (! $response->successful()) {
            return ['status' => 'report request failed (HTTP '.$response->status().')'];
        }
        $run = (array) $response->json();
        for ($i = 0; ($run['status'] ?? null) !== 'completed' && $i < 12; $i++) {
            if (in_array($run['status'] ?? null, ['failed', 'cancelled'], true)) return ['status' => 'report '.$run['status']];
            $this->client->pause(5);
            $run = (array) $this->client->call($connection, 'report', (string) ($run['id'] ?? ''))->json();
        }
        if (($run['status'] ?? null) !== 'completed' || ! is_string($run['report_url'] ?? null)) {
            return ['status' => 'report still generating; run the check again in a minute'];
        }

        $rows = $this->ledger->parse($this->client->downloadReport($run['report_url']));
        $apiTips = 0.0;
        $apiTipCount = 0;
        foreach ($rows as $row) {
            if (strtoupper((string) ($row['transaction_type'] ?? '')) === 'TIP' && is_numeric($row['transaction_amount'] ?? null)) {
                $apiTips += (float) $row['transaction_amount'];
                $apiTipCount++;
            }
        }
        $ledgerTips = WhatnotLedgerEntry::query()
            ->where('whatnot_channel_id', $connection->whatnot_channel_id)
            ->whereRaw('LOWER(TRIM(transaction_type)) IN (?, ?)', ['tip', 'tips'])
            ->where(fn ($q) => $q->where('completed_date', '>=', $since)->orWhere(fn ($q) => $q->whereNull('completed_date')->where('created_date', '>=', $since)));

        return [
            'status' => 'compared',
            'api_tips' => round($apiTips, 2), 'api_tip_count' => $apiTipCount,
            'ledger_tips' => round((float) (clone $ledgerTips)->sum('amount'), 2), 'ledger_tip_count' => (clone $ledgerTips)->count(),
            'report_rows' => count($rows),
        ];
    }

    /** @param array<string,mixed> $report */
    private function finish(array $report): array
    {
        $differs = collect($report['shows'])->where('status', '!=', 'ok')->count();
        $tips = $report['tips'] ?? null;
        if (is_array($tips) && ($tips['status'] ?? null) === 'compared' && abs($tips['api_tips'] - $tips['ledger_tips']) > self::GROSS_TOLERANCE) {
            $report['problems'][] = "Tips differ: Whatnot \${$tips['api_tips']} ({$tips['api_tip_count']}) vs ledger \${$tips['ledger_tips']} ({$tips['ledger_tip_count']}).";
        }
        $report['verdict'] = $report['problems'] === [] && $differs === 0
            ? 'Everything compared matches. Safe to turn on WHATNOT_SELLER_APPLY_ENABLED.'
            : 'Differences found — review them before turning on WHATNOT_SELLER_APPLY_ENABLED.';

        WhatnotApiSyncAudit::create([
            'whatnot_channel_id' => $report['channel_id'],
            'resource' => 'verify',
            'status' => $report['problems'] === [] && $differs === 0 ? 'matched' : 'differences',
            'summary' => $report,
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        return $report;
    }
}
