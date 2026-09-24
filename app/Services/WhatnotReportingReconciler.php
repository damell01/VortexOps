<?php

namespace App\Services;

use App\Models\Shipment;
use App\Models\Show;
use App\Models\WhatnotBuyer;
use App\Models\WhatnotChannel;
use App\Models\WhatnotShowOrder;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WhatnotReportingReconciler
{
    private const ORDER_PAGE_SIZE = 100;

    public function __construct(
        private readonly WhatnotScraper $scraper,
        private readonly WhatnotDataNormalizer $normalizer,
    ) {}

    public function discoverShows(WhatnotChannel $channel, ?callable $progress = null): array
    {
        $progress && $progress('discovery: scanning Seller Hub Current, Upcoming, and Past with Scrapling');
        $index = $this->scraper->fetchSellerHubIndex($channel->whatnot_username, false, $progress);
        $created = $updated = $skipped = $flagged = 0;

        $groups = [
            'current' => (array) ($index['current'] ?? []),
            'upcoming' => (array) ($index['upcoming'] ?? []),
            'past' => (array) ($index['past'] ?? []),
        ];
        $totalSeen = array_sum(array_map('count', $groups));
        if ($totalSeen === 0) {
            throw new \RuntimeException('Seller Hub returned zero Current, Upcoming, and Past shows; refusing to treat an empty page as authoritative.');
        }

        $seenIds = [];
        foreach ($groups as $state => $rows) {
            foreach ($rows as $raw) {
                if (! is_array($raw)) { $skipped++; continue; }
                $liveId = strtolower(trim((string) ($raw['live_id'] ?? $raw['whatnot_live_id'] ?? '')));
                if ($liveId === '') { $skipped++; continue; }
                $seenIds[$liveId] = true;

                $normalized = $this->normalizer->normalizeShow($raw);
                $title = trim((string) ($normalized['title'] ?? $raw['title'] ?? ''));
                $date = $normalized['show_date'] ?? $raw['show_date'] ?? null;
                if ($title === '' || ! $date) { $skipped++; continue; }

                // Whatnot UUID is globally unique in our schema. Never create a
                // duplicate merely because a channel context was wrong.
                $show = Show::query()->where('whatnot_show_id', $liveId)->first();
                $channelMismatch = $show && $show->whatnot_channel_id && (int) $show->whatnot_channel_id !== (int) $channel->id;

                $previousRaw = $show && is_array($show->raw_import_payload) ? $show->raw_import_payload : [];
                $fields = array_filter([
                    'whatnot_channel_id' => $channelMismatch ? $show->whatnot_channel_id : $channel->id,
                    'channel_attribution_suspect' => $channelMismatch ? true : ($show?->channel_attribution_suspect ?? false),
                    'whatnot_show_id' => $liveId,
                    'title' => $title,
                    'show_date' => $date,
                    'start_time' => $normalized['start_time'] ?? $raw['start_time'] ?? null,
                    'detail_url' => $raw['detail_url'] ?? $raw['open_url'] ?? ('https://www.whatnot.com/dashboard/live/'.$liveId),
                    'import_source' => 'auto_whatnot',
                    'last_synced_at' => now(),
                    'raw_import_payload' => array_merge($previousRaw, $raw, [
                        '_seller_hub_state' => $state,
                        '_seller_hub_seen_at' => now()->toIso8601String(),
                    ]),
                ], fn ($v) => $v !== null);

                if ($show) {
                    $show->forceFill($fields)->save();
                    $updated++;
                } else {
                    $fields['status'] = 'draft';
                    $fields['created_by'] = 1;
                    $show = Show::create($fields);
                    $show->detectStreamers();
                    $created++;
                }
            }
        }

        // A formerly-upcoming show disappearing is suspicious, not proof of a
        // cancellation. Flag it for reconciliation instead of silently deleting
        // or cancelling a legitimate show.
        $future = Show::query()
            ->where('whatnot_channel_id', $channel->id)
            ->where('import_source', 'auto_whatnot')
            ->whereDate('show_date', '>=', today())
            ->get();
        foreach ($future as $show) {
            $raw = is_array($show->raw_import_payload) ? $show->raw_import_payload : [];
            if (($raw['_seller_hub_state'] ?? null) !== 'upcoming' || isset($seenIds[strtolower((string) $show->whatnot_show_id)])) continue;
            $raw['_seller_hub_missing_since'] ??= now()->toIso8601String();
            $show->forceFill(['raw_import_payload' => $raw])->saveQuietly();
            $flagged++;
        }

        $progress && $progress("discovery: {$created} created, {$updated} refreshed, {$skipped} skipped, {$flagged} missing-upcoming flagged");
        return array_merge(compact('created', 'updated', 'skipped', 'flagged'), [
            'counts' => [
                'current' => count($groups['current']),
                'upcoming' => count($groups['upcoming']),
                'past' => count($groups['past']),
            ],
        ]);
    }

    public function reconcileOrders(WhatnotChannel $channel, Carbon $since, int $batchSize = 25, ?callable $progress = null): array
    {
        $batchSize = max(1, min(30, $batchSize));

        $shows = Show::query()
            ->where('whatnot_channel_id', $channel->id)
            ->whereDate('show_date', '>=', $since->toDateString())
            ->whereDate('show_date', '<=', today())
            ->whereNotIn('status', ['cancelled'])
            ->whereNotNull('whatnot_show_id')
            ->withCount('orders')
            ->orderByRaw('CASE WHEN (SELECT COUNT(*) FROM whatnot_show_orders WHERE whatnot_show_orders.show_id = shows.id) > 5000 THEN 0 WHEN (SELECT COUNT(*) FROM whatnot_show_orders WHERE whatnot_show_orders.show_id = shows.id) = 0 THEN 1 ELSE 2 END')
            ->orderBy('show_date')
            ->orderBy('id')
            ->get();

        $checked = $replaced = $created = $rejected = $skipped = 0;

        foreach ($shows->chunk($batchSize) as $chunk) {
            $sources = [];
            $byKey = [];

            foreach ($chunk as $show) {
                $liveId = $this->liveId($show);
                if (! $liveId) {
                    $skipped++;
                    continue;
                }

                $sources[] = ['live_id' => $liveId, 'show_key' => $show->id];
                $byKey[(string) $show->id] = $show;
            }

            if ($sources === []) {
                continue;
            }

            $progress && $progress('orders: scraping '.count($sources).' show(s) as one verified channel batch');
            $rowsByShow = $this->scraper->fetchOrdersForShows($sources, $channel->whatnot_username, false, $progress);

            foreach ($sources as $source) {
                $key = (string) $source['show_key'];
                $show = $byKey[$key] ?? null;
                if (! $show) {
                    continue;
                }

                $checked++;

                if (! array_key_exists($key, $rowsByShow) && ! array_key_exists((int) $key, $rowsByShow)) {
                    $rejected++;
                    $progress && $progress("orders: show #{$show->id} rejected — scraper returned no keyed result");
                    continue;
                }

                $rows = $rowsByShow[$key] ?? $rowsByShow[(int) $key] ?? [];

                if (! is_array($rows)) {
                    $rejected++;
                    $progress && $progress("orders: show #{$show->id} rejected — scraper returned a non-array result");
                    continue;
                }

                $incomingCount = count($rows);

                // Whatnot currently serves the orders table in 100-row pages. If the
                // scraper returns exactly one full page, we cannot prove the result is
                // complete unless pagination metadata says so. The batch scraper does
                // not currently expose that proof to PHP, therefore fail closed here.
                // This prevents a truncated 100-row scrape from deleting authoritative
                // rows that may exist beyond page one.
                if ($incomingCount === self::ORDER_PAGE_SIZE) {
                    $rejected++;
                    $progress && $progress(
                        "orders: show #{$show->id} protected — exactly ".self::ORDER_PAGE_SIZE.
                        ' rows returned; pagination completeness is ambiguous, existing rows preserved'
                    );
                    Log::warning('Whatnot order reconciliation protected an ambiguous full page', [
                        'show_id' => $show->id,
                        'whatnot_show_id' => $show->whatnot_show_id,
                        'existing_rows' => WhatnotShowOrder::where('show_id', $show->id)->count(),
                        'incoming_rows' => $incomingCount,
                    ]);
                    continue;
                }

                if (! $this->orderBatchLooksPlausible($show, $rows)) {
                    $rejected++;
                    $progress && $progress("orders: show #{$show->id} rejected — implausible row count {$incomingCount}");
                    continue;
                }

                $before = WhatnotShowOrder::where('show_id', $show->id)->count();

                $result = DB::transaction(function () use ($show, $rows) {
                    WhatnotShowOrder::where('show_id', $show->id)->delete();
                    $result = $this->scraper->persistShowOrders($show, $rows);
                    $show->updateQuietly(['last_synced_at' => now()]);
                    return $result;
                });

                $replaced += $before;
                $created += (int) ($result['created'] ?? 0);
                $progress && $progress("orders: show #{$show->id} reconciled — {$before} old row(s) replaced with ".(int) ($result['created'] ?? 0));
            }
        }

        $buyers = $this->rebuildBuyers($channel, $since);
        $progress && $progress("buyers: {$buyers['created']} created, {$buyers['updated']} updated");

        return compact('checked', 'replaced', 'created', 'rejected', 'skipped', 'buyers');
    }

    public function backfillAnalytics(WhatnotChannel $channel, Carbon $since, ?int $limit = 25, ?callable $progress = null): array
    {
        // Historical analytics is intentionally channel-wide. Seller Hub's Past
        // list is already the authoritative traversal, so keep one authenticated
        // browser/session alive and visit each show's See Analytics destination.
        // This replaces the old DB-missing-show -> UUID -> new browser loop.
        $progress && $progress(
            'analytics: walking Seller Hub Past shows once for @'.$channel->whatnot_username.
            ' since '.$since->toDateString()
        );

        // A null/zero limit means no artificial cap: process every incomplete show.
        // Explicit limits are still honored for smoke tests or targeted runs.
        $batchSize = $limit === null || $limit <= 0 ? null : max(1, (int) $limit);
        $targets = Show::query()
            ->where('whatnot_channel_id', $channel->id)
            ->whereDate('show_date', '>=', $since->toDateString())
            ->whereDate('show_date', '<=', today())
            ->whereNotIn('status', ['cancelled'])
            ->whereNotNull('whatnot_show_id')
            ->missingAnalytics()
            ->orderByDesc('show_date')
            ->orderByDesc('id')
            ->when($batchSize !== null, fn ($q) => $q->limit($batchSize))
            ->pluck('whatnot_show_id')
            ->map(fn ($id) => strtolower(trim((string) $id)))
            ->filter()
            ->values()
            ->all();

        if ($targets === []) {
            $progress && $progress('analytics: no missing analytics targets remain for this channel');
            return ['updated' => 0, 'failed' => 0, 'skipped' => 0];
        }

        $progress && $progress('analytics: targeting '.count($targets).' incomplete show(s)'.($batchSize === null ? ' (all)' : ''));

        // Keep browser batches small, but exhaust every analytics candidate that
        // can actually be matched for this channel during this run. Never retry a
        // returned UUID in the same run: a show can legitimately remain incomplete
        // when Whatnot has not published settlement/duration yet.
        $updated = $failed = $skipped = 0;
        $seen = [];
        $pendingTargets = array_values(array_unique($targets));
        $batchNumber = 0;

        while ($pendingTargets !== []) {
            $batchNumber++;
            $progress && $progress(
                'analytics: batch '.$batchNumber.' requesting '.count($pendingTargets).
                ' not-yet-attempted target(s)'
            );

            $rawRows = $this->scraper->fetchHistoricalAnalytics(
                since: $since->toDateString(),
                channelUsername: $channel->whatnot_username,
                onProgress: $progress,
                targetLiveIds: $pendingTargets,
                batchSize: 10,
            );

            if ($rawRows === []) {
                $unavailableAt = now();
                Show::query()
                    ->where('whatnot_channel_id', $channel->id)
                    ->whereIn('whatnot_show_id', $pendingTargets)
                    ->update([
                        'analytics_sync_status' => 'unavailable',
                        'analytics_sync_note' => 'Not present as an analytics candidate in the verified Seller Hub Past index.',
                        'analytics_unavailable_at' => $unavailableAt,
                    ]);
                $progress && $progress(
                    'analytics: no additional Seller Hub analytics candidates matched; '.
                    count($pendingTargets).' target(s) marked unavailable (eligible for retry in 7 days)'
                );
                break;
            }

            $returnedIds = [];
            foreach ($rawRows as $raw) {
                if (! is_array($raw)) {
                    continue;
                }
                $returnedId = strtolower(trim((string) ($raw['whatnot_live_id'] ?? $raw['live_id'] ?? '')));
                if ($returnedId !== '') {
                    $returnedIds[$returnedId] = true;
                }
            }

            if ($returnedIds === []) {
                $progress && $progress('analytics: batch returned no identifiable UUIDs; stopping to avoid a retry loop');
                break;
            }

            $pendingTargets = array_values(array_filter(
                $pendingTargets,
                fn ($id) => ! isset($returnedIds[strtolower(trim((string) $id))])
            ));

            foreach ($rawRows as $raw) {
            if (! is_array($raw)) {
                $skipped++;
                continue;
            }

            $liveId = strtolower(trim((string) ($raw['whatnot_live_id'] ?? $raw['live_id'] ?? '')));
            if ($liveId === '' || isset($seen[$liveId])) {
                $skipped++;
                continue;
            }
            $seen[$liveId] = true;

            $show = Show::query()
                ->where('whatnot_channel_id', $channel->id)
                ->where(function ($q) use ($liveId) {
                    $q->where('whatnot_show_id', $liveId)
                        ->orWhere('detail_url', 'like', '%'.$liveId.'%');
                })
                ->first();

            if (! $show) {
                $skipped++;
                $progress && $progress("analytics: Seller Hub uuid={$liveId} is not in this channel's database yet; discovery will import it");
                continue;
            }

            try {
                $normalized = $this->normalizer->normalizeShow($raw);

                // Whatnot explicitly reporting a zero-minute duration on a show
                // at least two calendar days old means the scheduled show never
                // actually happened. Do not confuse this with a missing/null
                // duration, which remains eligible for analytics retry.
                $explicitZeroDuration = array_key_exists('show_duration', $raw)
                    && $raw['show_duration'] !== null
                    && (int) $raw['show_duration'] === 0;
                $showDate = $show->show_date ? \Illuminate\Support\Carbon::parse($show->show_date)->startOfDay() : null;
                $isOldEnoughNoShow = $showDate
                    && $showDate->lte(now()->startOfDay()->subDays(2));

                if ($explicitZeroDuration && $isOldEnoughNoShow) {
                    $showId = $show->id;
                    $showDateDisplay = $showDate->toDateString();
                    $show->delete();
                    $updated++;
                    $progress && $progress(
                        "analytics: show #{$showId} removed · {$showDateDisplay} · Whatnot reported 0-minute duration on a show at least 2 days old"
                    );
                    continue;
                }

                $fields = [];
                foreach (['gross_revenue','whatnot_net','completed_earnings','avg_order_value','giveaway_spend','units_sold','giveaways_count','buyers_count','first_time_buyers','returning_buyers','shares_count','max_concurrent_viewers','total_views','show_duration'] as $field) {
                    if (($normalized[$field] ?? null) !== null) {
                        $fields[$field] = $normalized[$field];
                    }
                }

                if ($fields === []) {
                    $failed++;
                    $progress && $progress("analytics: show #{$show->id} returned no usable metrics");
                    continue;
                }

                $fields['last_synced_at'] = now();
                $fields['last_analytics_synced_at'] = now();
                $fields['raw_import_payload'] = $raw;
                $show->forceFill($fields)->save();

                $fresh = $show->fresh();
                $analyticsComplete = $fresh->gross_revenue !== null
                    && $fresh->show_duration !== null
                    && ($fresh->whatnot_net !== null || $fresh->completed_earnings !== null);
                $fresh->forceFill([
                    'analytics_sync_status' => $analyticsComplete ? 'complete' : 'partial',
                    'analytics_sync_note' => $analyticsComplete
                        ? 'Seller Hub analytics imported.'
                        : 'Seller Hub analytics reached; one or more optional/settlement metrics are still unavailable.',
                    'analytics_unavailable_at' => null,
                ])->saveQuietly();
                $updated++;

                $fresh = $fresh->fresh();
                $netDisplay = $fresh->whatnot_net === null
                    ? 'unavailable'
                    : '$'.number_format((float) $fresh->whatnot_net, 2);

                $progress && $progress(
                    "analytics: show #{$show->id} updated · {$fresh->show_date} · gross $".
                    number_format((float) ($fresh->gross_revenue ?? 0), 2).
                    " · net {$netDisplay} · status {$fresh->analytics_sync_status}"
                );
            } catch (\Throwable $e) {
                $failed++;
                $progress && $progress("analytics: show #{$show->id} failed — {$e->getMessage()}");
                Log::warning('Channel-wide Whatnot analytics update failed', [
                    'show_id' => $show->id,
                    'channel' => $channel->whatnot_username,
                    'exception' => $e->getMessage(),
                ]);
            }
            }
        }

        $progress && $progress(
            'analytics: channel walk complete · '.number_format(count($seen)).
            " unique show(s) returned · {$updated} updated · {$failed} failed · {$skipped} skipped · ".
            number_format(count($pendingTargets)).' unavailable/not matched this run'
        );

        return compact('updated', 'failed', 'skipped');
    }

    public function reconcileShipments(WhatnotChannel $channel, Carbon $since, int $batchSize = 25, ?callable $progress = null, ?int $maxShows = null): array
    {
        $batchSize = max(1, min(30, $batchSize));
        $shows = Show::query()->where('whatnot_channel_id', $channel->id)
            ->whereDate('show_date', '>=', $since->toDateString())->whereDate('show_date', '<=', today())
            ->whereNotIn('status', ['cancelled'])->whereNotNull('whatnot_show_id')->orderBy('show_date')->orderBy('id')
            ->when($maxShows !== null, fn ($q) => $q->limit(max(1, $maxShows)))->get();
        $checked = $created = $updated = $skipped = 0;

        foreach ($shows->chunk($batchSize) as $chunk) {
            $before = Shipment::whereIn('show_id', $chunk->pluck('id'))->count();
            $progress && $progress("shipments: scraping {$chunk->count()} show(s)");
            $result = $this->scraper->refreshShipmentsForShows($chunk, $channel->whatnot_username);
            $after = Shipment::whereIn('show_id', $chunk->pluck('id'))->count();
            $created += max(0, $after - $before);
            $updated += max(0, (int) ($result['updated'] ?? 0) - max(0, $after - $before));
            $skipped += (int) ($result['skipped_shows'] ?? 0);
            $checked += $chunk->count();
        }

        return compact('checked', 'created', 'updated', 'skipped');
    }

    private function rebuildBuyers(WhatnotChannel $channel, Carbon $since): array
    {
        $usernames = WhatnotShowOrder::query()->join('shows', 'whatnot_show_orders.show_id', '=', 'shows.id')
            ->where('shows.whatnot_channel_id', $channel->id)->whereDate('shows.show_date', '>=', $since->toDateString())
            ->whereNotNull('whatnot_show_orders.buyer_username')->where('whatnot_show_orders.buyer_username', '<>', '')
            ->distinct()->pluck('whatnot_show_orders.buyer_username');
        $created = $updated = 0;

        foreach ($usernames as $username) {
            $agg = WhatnotShowOrder::query()->join('shows', 'whatnot_show_orders.show_id', '=', 'shows.id')
                ->where('shows.whatnot_channel_id', $channel->id)->whereDate('shows.show_date', '>=', $since->toDateString())
                ->where('whatnot_show_orders.buyer_username', $username)
                ->selectRaw('COUNT(*) as total_orders, SUM(whatnot_show_orders.total_price) as lifetime_spend, MIN(whatnot_show_orders.show_date) as first_purchase_date, MAX(whatnot_show_orders.show_date) as last_purchase_date, MAX(whatnot_show_orders.buyer_display_name) as display_name')->first();
            $totalOrders = (int) ($agg->total_orders ?? 0);
            $lifetimeSpend = (float) ($agg->lifetime_spend ?? 0);
            $attrs = ['total_orders' => $totalOrders, 'lifetime_spend' => $lifetimeSpend, 'avg_order_value' => $totalOrders > 0 ? round($lifetimeSpend / $totalOrders, 2) : null, 'first_purchase_date' => $agg->first_purchase_date ?? null, 'last_purchase_date' => $agg->last_purchase_date ?? null, 'display_name' => ($agg->display_name ?? null) ?: null];
            $buyer = WhatnotBuyer::where('username', $username)->first();
            if ($buyer) {
                $buyer->update($attrs);
                $updated++;
            } else {
                WhatnotBuyer::create(['username' => $username] + $attrs);
                $created++;
            }
        }

        if ($usernames->isNotEmpty()) {
            DB::statement('UPDATE whatnot_show_orders o JOIN whatnot_buyers b ON b.username = o.buyer_username SET o.whatnot_buyer_id = b.id WHERE o.whatnot_buyer_id IS NULL AND o.buyer_username IS NOT NULL');
        }

        return compact('created', 'updated');
    }

    private function orderBatchLooksPlausible(Show $show, array $rows): bool
    {
        $count = count($rows);
        if ($count > 5000) return false;
        $units = (int) ($show->units_sold ?? 0);
        if ($units > 0 && $count > max(500, ($units * 3) + 100)) return false;

        $ids = [];
        foreach ($rows as $row) {
            if (! is_array($row)) return false;
            $id = trim((string) ($row['order_id'] ?? ''));
            if ($id !== '') $ids[] = $id;
        }
        if (count($ids) > 20 && count(array_unique($ids)) < (int) floor(count($ids) * 0.8)) return false;

        return true;
    }

    private function liveId(Show $show): ?string
    {
        foreach ([$show->whatnot_show_id, $show->detail_url] as $value) {
            if (preg_match('/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i', (string) $value, $m)) {
                return strtolower($m[0]);
            }
        }

        return null;
    }
}
