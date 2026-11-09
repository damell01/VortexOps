<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\Show;
use App\Models\WhatnotApiConnection;
use App\Models\WhatnotApiSyncAudit;
use App\Models\WhatnotShowOrder;
use Illuminate\Support\Carbon;

/**
 * Backfill one channel from the Seller API back to a start date, in steps:
 * shows → show analytics → orders → line items → shipments → ledger
 * (financial report, a month at a time) → daily overview.
 *
 * Each step is safe to repeat. Finished steps are remembered (Setting
 * whatnot_api_backfill:{channel}), so a run that stops part way picks up
 * at the step it was on; --fresh starts over.
 */
final class WhatnotApiBackfill
{
    public const STEPS = ['shows', 'analytics', 'orders', 'items', 'shipments', 'ledger', 'overview'];

    public function __construct(
        private readonly WhatnotSellerApiClient $client,
        private readonly WhatnotApiReadOnlySync $snapshots,
        private readonly WhatnotApiShowImporter $shows,
        private readonly WhatnotApiShowAnalyticsApplier $analytics,
        private readonly WhatnotApiOrderApplier $orders,
        private readonly WhatnotApiShipmentImporter $shipments,
        private readonly WhatnotApiLedgerImporter $ledger,
        private readonly WhatnotApiOverviewImporter $overview,
        private readonly WhatnotApiReports $reports,
    ) {}

    /**
     * @param list<string>|null $only steps to run (default: all not yet done)
     * @param callable(string):void|null $log
     * @return array<string,mixed>
     */
    public function run(WhatnotApiConnection $connection, Carbon $since, bool $dryRun = true, ?array $only = null, bool $fresh = false, ?callable $log = null): array
    {
        $log ??= fn () => null;
        $channelId = (int) $connection->whatnot_channel_id;
        $key = 'whatnot_api_backfill:'.$channelId;
        $state = $fresh || $dryRun ? [] : (json_decode((string) Setting::get($key, '{}'), true) ?: []);
        if (($state['since'] ?? null) !== $since->toDateString()) $state = ['since' => $since->toDateString(), 'done' => []];

        $summary = ['channel_id' => $channelId, 'since' => $since->toDateString(), 'dry_run' => $dryRun, 'steps' => [], 'errors' => []];
        // A preview must not inherit completion markers from a prior applied run.
        foreach (self::STEPS as $step) {
            if ($only !== null && ! in_array($step, $only, true)) continue;
            if (in_array($step, $state['done'] ?? [], true)) {
                $summary['steps'][$step] = 'already done';
                continue;
            }
            $log("— {$step}…");
            try {
                $summary['steps'][$step] = $this->{'step'.ucfirst($step)}($connection, $since, $dryRun, $log);
                $log("  {$step}: ".json_encode($summary['steps'][$step]));
                if (! $dryRun) {
                    $state['done'] = array_values(array_unique($state['done'] ?? []));
                    $state['done'][] = $step;
                    Setting::set($key, json_encode($state));
                }
            } catch (\Throwable $e) {
                report($e);
                $summary['errors'][] = "{$step}: ".$e->getMessage();
                $log("  {$step} stopped: ".$e->getMessage());
                break; // later steps depend on earlier ones (e.g. orders need shows)
            }
        }

        WhatnotApiSyncAudit::create([
            'whatnot_channel_id' => $channelId, 'resource' => 'backfill',
            'status' => $summary['errors'] ? 'partial' : ($dryRun ? 'dry_run' : 'applied'),
            'summary' => $summary, 'started_at' => now(), 'finished_at' => now(),
        ]);

        return $summary;
    }

    private function stepShows(WhatnotApiConnection $c, Carbon $since, bool $dryRun, callable $log): array
    {
        $counts = [];
        foreach (['ended' => true, 'stopped' => true, 'cancelled' => true, 'scheduled' => false] as $status => $stopAtSince) {
            $pageResult = $this->client->eachPage($c, 'shows', function (array $page) use ($c, $since, $dryRun, $stopAtSince, &$counts) {
                $oldest = null;
                foreach ($page['data'] as $show) {
                    if (! is_array($show)) continue;
                    $start = $show['scheduled_start_at'] ?? null;
                    if ($stopAtSince && is_string($start) && Carbon::parse($start)->lt($since)) continue;
                    $outcome = $this->shows->import((int) $c->whatnot_channel_id, $show, $dryRun)['outcome'];
                    $counts[$outcome] = ($counts[$outcome] ?? 0) + 1;
                    if (is_string($start)) $oldest = min($oldest ?? $start, $start);
                }
                return ! $stopAtSince || $oldest === null || Carbon::parse($oldest)->gte($since);
            }, ['status' => $status], maxPages: 400);
            if (! $pageResult['ok']) {
                throw new \RuntimeException('Show backfill failed for status '.$status.' (HTTP '.($pageResult['http_status'] ?? 'unknown').').');
            }
        }
        return $counts;
    }

    private function stepAnalytics(WhatnotApiConnection $c, Carbon $since, bool $dryRun, callable $log): array
    {
        $counts = [];
        $ids = Show::query()->where('whatnot_channel_id', $c->whatnot_channel_id)
            ->whereNotNull('whatnot_show_id')->where('whatnot_show_id', '!=', '')
            ->whereDate('show_date', '>=', $since)->whereDate('show_date', '<=', now())
            ->pluck('whatnot_show_id');
        foreach ($ids as $i => $id) {
            $response = $this->client->call($c, 'show_analytics', strtolower($id));
            if (! $response->successful()) {
                throw new \RuntimeException('Show analytics '.$id.' failed (HTTP '.$response->status().'); backfill cannot be marked complete.');
            }
            $outcome = $this->analytics->apply((int) $c->whatnot_channel_id, (array) $response->json(), $dryRun)['outcome'];
            $counts[$outcome] = ($counts[$outcome] ?? 0) + 1;
            if ($i % 50 === 49) $log('  analytics '.($i + 1).'/'.$ids->count());
        }
        return $counts;
    }

    private function stepOrders(WhatnotApiConnection $c, Carbon $since, bool $dryRun, callable $log): array
    {
        $counts = [];
        $pages = 0;
        $pageResult = $this->client->eachPage($c, 'orders', function (array $page) use ($c, $since, $dryRun, $log, &$counts, &$pages) {
            $snapshot = $this->snapshots->store((int) $c->whatnot_channel_id, 'orders', $page);
            $result = $this->orders->apply((int) $c->whatnot_channel_id, $snapshot['id'], $dryRun);
            foreach ($result['counts'] as $k => $n) $counts[$k] = ($counts[$k] ?? 0) + $n;
            if (++$pages % 20 === 0) $log("  orders: {$pages} pages");
            $dates = array_filter(array_map(fn ($o) => is_array($o) ? ($o['created_at'] ?? null) : null, $page['data']));
            return $dates === [] || Carbon::parse(min($dates))->gte($since);
        }, maxPages: 5000);
        if (! $pageResult['ok']) {
            throw new \RuntimeException('Order backfill stopped on HTTP '.($pageResult['http_status'] ?? 'unknown').'; do not mark complete.');
        }
        return $counts + ['pages' => $pages];
    }

    private function stepItems(WhatnotApiConnection $c, Carbon $since, bool $dryRun, callable $log): array
    {
        $counts = [];
        $orderIds = WhatnotShowOrder::query()
            ->whereHas('show', fn ($q) => $q->where('whatnot_channel_id', $c->whatnot_channel_id)->whereDate('show_date', '>=', $since))
            ->where(fn ($q) => $q->whereNull('item_name')->orWhere('item_name', ''))
            ->whereNotNull('whatnot_order_id')->pluck('whatnot_order_id')->unique()->values();
        foreach ($orderIds as $i => $orderId) {
            if (! preg_match('/^[A-Za-z0-9_-]{1,128}$/', (string) $orderId)) { $counts['invalid_id'] = ($counts['invalid_id'] ?? 0) + 1; continue; }
            $response = $this->client->call($c, 'order_items', (string) $orderId, ['limit' => WhatnotSellerApiClient::MAX_PAGE_SIZE]);
            if (! $response->successful()) {
                throw new \RuntimeException('Order '.$orderId.' items failed (HTTP '.$response->status().'); backfill cannot be marked complete.');
            }
            $outcome = $this->orders->applyItems((int) $c->whatnot_channel_id, (string) $orderId, (array) ($response->json('data') ?? []), $dryRun);
            $counts[$outcome] = ($counts[$outcome] ?? 0) + 1;
            if ($i % 200 === 199) $log('  items '.($i + 1).'/'.$orderIds->count());
        }
        return $counts;
    }

    private function stepShipments(WhatnotApiConnection $c, Carbon $since, bool $dryRun, callable $log): array
    {
        $counts = [];
        $seen = 0;
        $pageResult = $this->client->eachPage($c, 'shipments', function (array $page) use ($c, $since, $dryRun, $log, &$counts, &$seen) {
            $oldest = null;
            foreach ($page['data'] as $shipment) {
                if (! is_array($shipment) || ! is_string($shipment['id'] ?? null)) continue;
                $created = $shipment['created_at'] ?? null;
                if (is_string($created)) $oldest = min($oldest ?? $created, $created);
                $items = $this->client->call($c, 'shipment_items', $shipment['id'], ['limit' => WhatnotSellerApiClient::MAX_PAGE_SIZE]);
                if (! $items->successful()) {
                    throw new \RuntimeException('Shipment '.$shipment['id'].' items failed (HTTP '.$items->status().'); backfill cannot be marked complete.');
                }
                $outcome = $this->shipments->import((int) $c->whatnot_channel_id, $shipment, (array) ($items->json('data') ?? []), $dryRun)['outcome'];
                $counts[$outcome] = ($counts[$outcome] ?? 0) + 1;
                if (++$seen % 200 === 0) $log("  shipments: {$seen}");
            }
            return $oldest === null || Carbon::parse($oldest)->gte($since);
        }, maxPages: 5000);
        if (! $pageResult['ok']) {
            throw new \RuntimeException('Shipment backfill stopped on HTTP '.($pageResult['http_status'] ?? 'unknown').'; do not mark complete.');
        }
        return $counts;
    }

    private function stepLedger(WhatnotApiConnection $c, Carbon $since, bool $dryRun, callable $log): array
    {
        $counts = [];
        $end = now()->startOfHour();
        for ($from = $since->copy(); $from->lt($end); $from = $to) {
            $to = $from->copy()->addMonthNoOverflow()->min($end);
            $report = $this->reports->fetch($c, WhatnotApiReports::financial($from, $to), maxPolls: 60);
            if ($report['status'] !== 'completed') {
                throw new \RuntimeException('financial report '.$from->toDateString().'–'.$to->toDateString().': '.$report['status'].(isset($report['http_status']) ? ' (HTTP '.$report['http_status'].')' : ''));
            }
            $result = $this->ledger->import((int) $c->whatnot_channel_id, $report['csv'], $dryRun, backfillFrom: $since);
            foreach ($result['counts'] as $k => $n) $counts[$k] = ($counts[$k] ?? 0) + $n;
            $log('  ledger '.$from->toDateString().': '.json_encode($result['counts']));
        }
        return $counts;
    }

    private function stepOverview(WhatnotApiConnection $c, Carbon $since, bool $dryRun, callable $log): array
    {
        $report = $this->reports->fetch($c, WhatnotApiReports::overview($since, now()->startOfDay()), maxPolls: 60);
        if ($report['status'] !== 'completed') {
            throw new \RuntimeException('overview report: '.$report['status']);
        }
        return $this->overview->import((int) $c->whatnot_channel_id, $report['csv'], $dryRun);
    }
}
