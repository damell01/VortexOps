<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\User;
use App\Models\WhatnotShowOrder;
use App\Support\FulfillmentAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Item-level scan verification while packing.
 *
 * Enforced only where the order data is reliable enough: every one of the
 * buyer's orders must map to an inventory item with a quantity. Anything less
 * is reported as unsupported rather than treated as verified.
 */
final class FulfillmentVerificationService
{
    /** Seconds within which the same code scanned again is a scanner bounce, not a second item. */
    public const BOUNCE_SECONDS = 2;

    public function coverage(int $runId, int $buyerId): array
    {
        $run = DB::table('fulfillment_runs')->where('id', $runId)->first();
        $buyer = DB::table('fulfillment_run_buyers')->where('id', $buyerId)->where('run_id', $runId)->first();
        abort_unless($run && $buyer, 404);

        $expected = $this->expected($run, $buyer);
        if ($expected === null) {
            return ['complete' => false, 'supported' => false, 'expected' => 0, 'scanned' => 0, 'missing' => [], 'lines' => []];
        }

        $scans = DB::table('fulfillment_verifications')->where('run_id', $runId)->where('buyer_id', $buyerId)
            ->whereIn('result', ['matched', 'override'])->whereNotNull('product_id')->select('product_id')
            ->selectRaw('SUM(quantity) as total')->groupBy('product_id')->pluck('total', 'product_id');

        $missing = [];
        $verified = 0;
        $items = InventoryItem::query()->whereIn('id', $expected->keys())->get(['id', 'name', 'image_path'])->keyBy('id');
        $lines = [];
        foreach ($expected as $productId => $quantity) {
            $done = min((int) $quantity, (int) ($scans[$productId] ?? 0));
            $verified += $done;
            if ($done < $quantity) $missing[(int) $productId] = $quantity - $done;
            $item = $items->get($productId);
            $lines[] = ['product_id' => (int) $productId, 'name' => $item?->name ?? 'Item #' . $productId, 'expected' => (int) $quantity, 'verified' => $done,
                'image' => $item?->hasImage() ? $item->imageUrl() : null];
        }

        return ['complete' => ! $missing, 'supported' => true, 'expected' => (int) $expected->sum(),
            'scanned' => $verified, 'missing' => $missing, 'lines' => $lines];
    }

    /** product_id => expected quantity, or null when the orders do not map reliably. */
    private function expected(object $run, object $buyer): ?\Illuminate\Support\Collection
    {
        $ids = json_decode($buyer->order_ids ?: '[]', true);
        if (! is_array($ids) || ! $ids) return null;
        $orders = WhatnotShowOrder::query()->where('show_id', $run->show_id)
            ->whereIn('whatnot_order_id', array_map('strval', $ids))->get(['whatnot_order_id', 'inventory_item_id', 'quantity']);
        $found = $orders->pluck('whatnot_order_id')->map(fn ($id) => (string) $id)->all();
        if ($orders->isEmpty() || array_diff(array_map('strval', $ids), $found)
            || $orders->contains(fn ($order) => ! $order->inventory_item_id || (int) $order->quantity < 1)) {
            return null;
        }

        return $orders->groupBy('inventory_item_id')->map(fn ($group) => (int) $group->sum('quantity'));
    }

    public function verify(int $runId, int $buyerId, string $barcode, User $actor): int
    {
        $barcode = trim($barcode);
        if ($barcode === '' || mb_strlen($barcode) > 128) throw ValidationException::withMessages(['barcode' => 'Scan a valid barcode.']);

        $result = DB::transaction(function () use ($runId, $buyerId, $barcode, $actor) {
            $run = DB::table('fulfillment_runs')->where('id', $runId)->lockForUpdate()->first();
            $buyer = DB::table('fulfillment_run_buyers')->where('id', $buyerId)->where('run_id', $runId)->first();
            abort_unless($run && $run->status === 'packing' && $buyer, 404);
            abort_unless(FulfillmentAccess::canWorkRun($actor, $runId), 403);
            if ($buyer->status === 'packed') throw ValidationException::withMessages(['barcode' => 'This buyer is already marked packed. Reopen the buyer before scanning more items.']);
            if ($buyer->status === 'held' || DB::table('fulfillment_flags')->where('buyer_id', $buyerId)->where('status', 'open')->exists()) {
                throw ValidationException::withMessages(['barcode' => 'Buyer is on hold. Resolve issues first.']);
            }

            // A scanner can send the same code twice in a blink; count it once.
            $bounce = DB::table('fulfillment_verifications')->where('run_id', $runId)->where('buyer_id', $buyerId)
                ->where('barcode', $barcode)->where('verified_by', $actor->id)
                ->where('created_at', '>=', now()->subSeconds(self::BOUNCE_SECONDS))->exists();
            if ($bounce) throw ValidationException::withMessages(['barcode' => 'Same item scanned twice in a row: counted once.']);

            $expected = $this->expected($run, $buyer);
            if ($expected === null) throw ValidationException::withMessages(['barcode' => 'This buyer\'s orders are not mapped to inventory items, so scans cannot be checked. Pack by hand or ask a manager.']);

            $product = InventoryItem::findByScan($barcode);
            $wanted = $product ? (int) ($expected[$product->id] ?? 0) : 0;
            if ($wanted === 0) {
                DB::table('fulfillment_verifications')->insert([
                    'run_id' => $runId, 'buyer_id' => $buyerId, 'product_id' => $product?->id, 'verified_by' => $actor->id,
                    'barcode' => $barcode, 'result' => 'unmatched', 'quantity' => 0, 'created_at' => now(), 'updated_at' => now(),
                ]);
                FulfillmentLog::record('scan_rejected', $runId, $actor->id, ['barcode' => $barcode, 'product' => $product?->name], $buyerId);
                return 0;
            }
            $scanned = (int) DB::table('fulfillment_verifications')->where('run_id', $runId)->where('buyer_id', $buyerId)
                ->whereIn('result', ['matched', 'override'])->where('product_id', $product->id)->sum('quantity');
            if ($scanned >= $wanted) throw ValidationException::withMessages(['barcode' => 'All ' . $wanted . ' of this item are already scanned for this buyer.']);

            $id = DB::table('fulfillment_verifications')->insertGetId([
                'run_id' => $runId, 'buyer_id' => $buyerId, 'product_id' => $product->id, 'verified_by' => $actor->id,
                'barcode' => $barcode, 'result' => 'matched', 'quantity' => 1, 'created_at' => now(), 'updated_at' => now(),
            ]);
            FulfillmentLog::record('item_verified', $runId, $actor->id, ['barcode' => $barcode, 'product' => $product->name, 'count' => ($scanned + 1) . '/' . $wanted], $buyerId);

            return $id;
        });

        if ($result === 0) throw ValidationException::withMessages(['barcode' => 'Wrong item for this buyer. Scan rejected and recorded for review.']);

        return $result;
    }

    /** A manager accepts the items still missing scans (no barcode, bad label…), with a note. */
    public function approveException(int $runId, int $buyerId, User $actor, string $note): int
    {
        abort_unless(FulfillmentAccess::isManager($actor), 403);
        if (trim($note) === '') throw ValidationException::withMessages(['note' => 'Say why the exception is approved.']);
        $coverage = $this->coverage($runId, $buyerId);
        if (! $coverage['supported'] || $coverage['complete']) return 0;

        return DB::transaction(function () use ($runId, $buyerId, $actor, $note, $coverage) {
            $count = 0;
            foreach ($coverage['missing'] as $productId => $quantity) {
                DB::table('fulfillment_verifications')->insert([
                    'run_id' => $runId, 'buyer_id' => $buyerId, 'product_id' => $productId, 'verified_by' => $actor->id,
                    'barcode' => 'manager-exception', 'result' => 'override', 'quantity' => $quantity,
                    'note' => mb_substr(trim($note), 0, 500), 'created_at' => now(), 'updated_at' => now(),
                ]);
                $count += $quantity;
            }
            FulfillmentLog::record('scan_override', $runId, $actor->id, ['items' => $count, 'note' => trim($note)], $buyerId);

            return $count;
        });
    }
}
