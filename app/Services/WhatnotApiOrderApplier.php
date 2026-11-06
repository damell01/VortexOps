<?php

namespace App\Services;

use App\Models\Show;
use App\Models\WhatnotApiSyncAudit;
use App\Models\WhatnotShowOrder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Apply a captured Seller API orders snapshot to whatnot_show_orders — the
 * table the scraper fills and every report, shipment and show page reads.
 *
 * Only fields Whatnot documents for an Order are trusted (id, show_id,
 * status, amounts, timestamps, transaction_type; see WhatnotV2OrderMapper).
 * The rules, each of which fails safe:
 *
 *  - An order joins a show only by an exact Whatnot show ID already on that
 *    show in this channel. No title/date guessing; no show → not applied.
 *  - No double counting: if a show already has scraper orders and none of
 *    the API's order IDs for it match them, the two ID formats differ and
 *    that whole show is skipped and reported, never duplicated.
 *  - An order ID already filed under another show is never moved.
 *  - Cancelled or failed orders are never created as sales; an existing row
 *    only has its status updated.
 *  - Updates touch API-owned fields only (status, amounts, order time) —
 *    never inventory mapping, costs, item names or anything set by hand.
 *
 * Dry run is always allowed and writes nothing. Writing needs production,
 * live requests, a verified contract and WHATNOT_SELLER_APPLY_ENABLED.
 */
final class WhatnotApiOrderApplier
{
    public const NOT_A_SALE = ['cancelled', 'failed'];

    public function __construct(private readonly WhatnotV2OrderMapper $mapper) {}

    public static function writesAllowed(): bool
    {
        return app()->environment('production')
            && config('services.whatnot_seller.live_requests_enabled', false)
            && config('services.whatnot_seller.contract_verified', false)
            && config('services.whatnot_seller.apply_enabled', false);
    }

    /** The newest orders snapshot for a channel, or null. */
    public function latestSnapshotId(int $channelId): ?int
    {
        $id = DB::table('whatnot_api_snapshots')
            ->where('whatnot_channel_id', $channelId)->where('resource', 'orders')
            ->orderByDesc('fetched_at')->orderByDesc('id')->value('id');

        return $id === null ? null : (int) $id;
    }

    /** The snapshot a capture just stored, found by its payload hash. */
    public function snapshotIdForHash(int $channelId, string $sha256): ?int
    {
        $id = DB::table('whatnot_api_snapshots')
            ->where('whatnot_channel_id', $channelId)->where('resource', 'orders')
            ->where('payload_sha256', $sha256)->value('id');

        return $id === null ? null : (int) $id;
    }

    /**
     * @return array{snapshot_id:int, channel_id:int, dry_run:bool, counts:array<string,int>, skipped_shows:array<int,string>}
     */
    public function apply(int $channelId, int $snapshotId, bool $dryRun = true): array
    {
        if (! $dryRun && ! self::writesAllowed()) {
            throw new RuntimeException('Applying API orders is disabled: it needs production, live requests, a verified contract and WHATNOT_SELLER_APPLY_ENABLED.');
        }

        $items = $this->snapshotItems($channelId, $snapshotId);
        $counts = [
            'received' => count($items), 'created' => 0, 'updated' => 0, 'unchanged' => 0,
            'invalid' => 0, 'no_show_id' => 0, 'show_not_found' => 0, 'show_ambiguous' => 0,
            'other_show' => 0, 'not_a_sale' => 0, 'id_format_mismatch' => 0, 'duplicate_in_payload' => 0,
            'needs_items' => [],
        ];

        // Map and group by show, failing closed on anything malformed.
        $byShow = [];
        $seen = [];
        foreach ($items as $item) {
            // Orders outside a live show (e.g. marketplace Buy It Now) have no
            // show to belong to; they are counted, not applied.
            if (is_array($item) && array_key_exists('show_id', $item) && $item['show_id'] === null) {
                $counts['no_show_id']++;
                continue;
            }
            try {
                $order = $this->mapper->map(is_array($item) ? $item : []);
            } catch (\InvalidArgumentException) {
                $counts['invalid']++;
                continue;
            }
            if (isset($seen[$order['whatnot_order_id']])) {
                $counts['duplicate_in_payload']++;
                continue;
            }
            $seen[$order['whatnot_order_id']] = true;
            $byShow[$order['whatnot_show_id']][] = $order;
        }

        $skippedShows = [];
        $run = function () use ($channelId, $byShow, $dryRun, &$counts, &$skippedShows) {
            foreach ($byShow as $whatnotShowId => $orders) {
                $shows = Show::query()
                    ->where('whatnot_channel_id', $channelId)
                    ->whereRaw('LOWER(whatnot_show_id) = ?', [$whatnotShowId])
                    ->get();
                if ($shows->count() !== 1) {
                    $counts[$shows->isEmpty() ? 'show_not_found' : 'show_ambiguous'] += count($orders);
                    continue;
                }
                $show = $shows->first();

                if ($this->idFormatsDiffer($show, $orders)) {
                    $counts['id_format_mismatch'] += count($orders);
                    $skippedShows[$show->id] = 'Show already has scraper orders and none of the API order IDs match them; not applied to avoid double counting.';
                    continue;
                }

                // A partial API page can contain a matching ID while other IDs
                // belong to scraper rows under a different identifier. Until a
                // complete show-level reconciliation exists, never create extra
                // sales on a show with scraper-origin orders. Existing canonical
                // IDs may still be refreshed without duplicating revenue.
                $hasScraperOrders = WhatnotShowOrder::query()
                    ->where('show_id', $show->id)
                    ->whereNotNull('whatnot_order_id')
                    ->get(['raw_data'])
                    ->contains(fn ($row) => ($row->raw_data['_source'] ?? null) !== 'seller_api');
                foreach ($orders as $order) {
                    if ($hasScraperOrders && ! WhatnotShowOrder::query()
                        ->where('show_id', $show->id)
                        ->where('whatnot_order_id', $order['whatnot_order_id'])->exists()) {
                        $counts['id_format_mismatch']++;
                        $skippedShows[$show->id] = 'Existing scraper sales require full-show reconciliation before new API orders may be created.';
                        continue;
                    }
                    $this->applyOne($show, $order, $dryRun, $counts);
                }
            }
        };

        $dryRun ? $run() : DB::transaction($run);

        WhatnotApiSyncAudit::create([
            'whatnot_channel_id' => $channelId,
            'resource' => 'orders_apply',
            'status' => $dryRun ? 'dry_run' : 'applied',
            'received_count' => $counts['received'],
            'matched_count' => $counts['created'] + $counts['updated'] + $counts['unchanged'],
            'unmatched_count' => $counts['show_not_found'] + $counts['show_ambiguous'] + $counts['id_format_mismatch'],
            'summary' => ['snapshot_id' => $snapshotId, 'counts' => array_diff_key($counts, ['needs_items' => true]), 'skipped_shows' => $skippedShows],
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        $needsItems = $counts['needs_items'];
        unset($counts['needs_items']);

        return ['snapshot_id' => $snapshotId, 'channel_id' => $channelId, 'dry_run' => $dryRun,
            'counts' => $counts, 'skipped_shows' => $skippedShows, 'needs_items' => $needsItems];
    }

    /** @param array<string,mixed> $order */
    private function applyOne(Show $show, array $order, bool $dryRun, array &$counts): void
    {
        $existing = WhatnotShowOrder::query()->where('whatnot_order_id', $order['whatnot_order_id'])->get();

        if ($existing->contains(fn ($row) => (int) $row->show_id !== (int) $show->id)) {
            $counts['other_show']++;
            return;
        }

        $fields = array_filter([
            'status' => $order['status'],
            'total_price' => $order['subtotal_usd'],
            'shipping_amount' => $order['shipping_usd'],
            'tax_amount' => $order['tax_usd'],
        ], fn ($v) => $v !== null);
        $stamp = ['received_at' => now()->toIso8601String()] + $order;
        // Filled only where empty: the scraper's or someone's own value wins.
        $fillIfEmpty = array_filter([
            'ordered_at_whatnot' => $order['created_at_source'],
            'buyer_username' => $order['buyer_username'],
            'buyer_display_name' => $order['buyer_display_name'],
            'whatnot_order_detail_url' => $order['url'],
        ], fn ($v) => $v !== null);

        if ($existing->isNotEmpty()) {
            $row = $existing->first();
            $changes = array_filter($fields, fn ($v, $k) => (string) $row->{$k} !== (string) $v, ARRAY_FILTER_USE_BOTH);
            foreach ($fillIfEmpty as $field => $value) {
                if (blank($row->{$field})) $changes[$field] = $value;
            }
            $raw = $row->raw_data ?? [];
            $needsEvidence = ($raw['livestream_id'] ?? null) !== $order['whatnot_show_id'];
            if (blank($row->item_name)) {
                $counts['needs_items'][] = $order['whatnot_order_id'];
            }
            if ($changes === [] && ! $needsEvidence) {
                $counts['unchanged']++;
                return;
            }
            $counts[$changes === [] ? 'unchanged' : 'updated']++;
            if (! $dryRun) {
                $raw['_seller_api'] = $stamp;
                // The API's own show for this order: evidence the ledger
                // matcher (LedgerAttributionService) accepts for attribution.
                $raw['livestream_id'] = $order['whatnot_show_id'];
                $row->forceFill($changes + ['raw_data' => $raw])->save();
            }
            return;
        }

        if (in_array($order['status'], self::NOT_A_SALE, true)) {
            $counts['not_a_sale']++;
            return;
        }

        $counts['created']++;
        $counts['needs_items'][] = $order['whatnot_order_id'];
        if ($dryRun) {
            return;
        }

        WhatnotShowOrder::create($fields + $fillIfEmpty + [
            'show_id' => $show->id,
            'whatnot_order_id' => $order['whatnot_order_id'],
            'whatnot_show_url' => $show->detail_url,
            'inventory_location_id' => $show->defaultInventoryLocation()?->id,
            'quantity' => 1,
            'unit_price' => $order['subtotal_usd'],
            'show_date' => $show->show_date,
            'raw_data' => ['_source' => 'seller_api', 'livestream_id' => $order['whatnot_show_id'], '_seller_api' => $stamp],
        ]);
    }

    /**
     * Fill an order's item details from GET /orders/{id}/items (OrderItem:
     * purchased_item.title/description/sku, quantity, unit_price). Only blank
     * names are filled; quantity and unit price only on rows the API created.
     *
     * @param list<array<string,mixed>> $items
     * @return 'filled'|'unchanged'|'no_row'|'no_items'
     */
    public function applyItems(int $channelId, string $orderId, array $items, bool $dryRun = true): string
    {
        if (! $dryRun && ! self::writesAllowed()) {
            throw new RuntimeException('Applying API orders is disabled.');
        }
        $row = WhatnotShowOrder::query()
            ->where('whatnot_order_id', $orderId)
            ->whereHas('show', fn ($q) => $q->where('whatnot_channel_id', $channelId))
            ->first();
        if (! $row) {
            return 'no_row';
        }

        $titles = [];
        $quantity = 0;
        $unit = null;
        $skus = [];
        $description = null;
        foreach ($items as $item) {
            if (! is_array($item)) continue;
            $purchased = is_array($item['purchased_item'] ?? null) ? $item['purchased_item'] : [];
            if (is_string($purchased['title'] ?? null) && trim($purchased['title']) !== '') $titles[] = trim($purchased['title']);
            if (is_string($purchased['sku'] ?? null) && $purchased['sku'] !== '') $skus[] = $purchased['sku'];
            $quantity += max(0, (int) ($item['quantity'] ?? 0));
            $money = $item['unit_price'] ?? null;
            if ($unit === null && is_array($money) && ($money['currency'] ?? null) === 'USD' && is_string($money['amount'] ?? null)
                && preg_match('/^-?\d{1,12}(?:\.\d{1,2})?$/', $money['amount'])) {
                $unit = $money['amount'];
            }
            $description ??= is_string($purchased['description'] ?? null) ? $purchased['description'] : null;
        }
        if ($titles === []) {
            return 'no_items';
        }

        $changes = [];
        if (blank($row->item_name)) $changes['item_name'] = mb_substr(implode(' + ', array_unique($titles)), 0, 255);
        if (blank($row->item_description) && $description) $changes['item_description'] = $description;
        if ((($row->raw_data['_source'] ?? null) === 'seller_api')) {
            if ($quantity > 0 && (int) $row->quantity !== $quantity) $changes['quantity'] = $quantity;
            if ($unit !== null && (string) $row->unit_price !== (string) $unit) $changes['unit_price'] = $unit;
        }
        if ($changes === []) {
            return 'unchanged';
        }
        if (! $dryRun) {
            $raw = $row->raw_data ?? [];
            $raw['_seller_api_items'] = ['titles' => $titles, 'skus' => $skus, 'quantity' => $quantity, 'received_at' => now()->toIso8601String()];
            $row->forceFill($changes + ['raw_data' => $raw])->save();
        }

        return 'filled';
    }

    /**
     * True when the show already holds scraper orders and none of this
     * snapshot's order IDs for it are among them: the two sources number
     * orders differently, so applying would count every sale twice.
     *
     * @param list<array<string,mixed>> $orders
     */
    private function idFormatsDiffer(Show $show, array $orders): bool
    {
        $scraperIds = WhatnotShowOrder::query()
            ->where('show_id', $show->id)
            ->whereNotNull('whatnot_order_id')
            ->get(['whatnot_order_id', 'raw_data'])
            ->reject(fn ($row) => ($row->raw_data['_source'] ?? null) === 'seller_api')
            ->pluck('whatnot_order_id')
            ->map(fn ($id) => (string) $id)
            ->all();

        if ($scraperIds === []) {
            return false;
        }

        $apiIds = array_map(fn ($o) => (string) $o['whatnot_order_id'], $orders);

        return array_intersect($apiIds, $scraperIds) === [];
    }

    /** @return list<mixed> */
    private function snapshotItems(int $channelId, int $snapshotId): array
    {
        $row = DB::table('whatnot_api_snapshots')
            ->where('whatnot_channel_id', $channelId)
            ->where('resource', 'orders')
            ->where('id', $snapshotId)
            ->first();
        if (! $row) {
            throw new RuntimeException('Order snapshot not found for this channel.');
        }
        $json = Crypt::decryptString($row->payload);
        if (! hash_equals($row->payload_sha256, hash('sha256', $json))) {
            throw new RuntimeException('Snapshot integrity check failed.');
        }
        $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $items = $payload['data'] ?? null;
        if (! is_array($items) || ! array_is_list($items)) {
            throw new RuntimeException('Unverified order response shape; expected a data list.');
        }

        return $items;
    }
}
