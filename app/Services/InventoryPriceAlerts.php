<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class InventoryPriceAlerts
{
    /**
     * Pending changes after the user's last explicit acknowledgement.
     * Return a fixed-size batch to keep login responsive on large catalogs.
     */
    public function pending(User $user, int $limit = 100): array
    {
        $cursor = (int) (DB::table('inventory_price_acknowledgments')
            ->where('user_id', $user->id)->value('last_event_id') ?? 0);

        return DB::table('inventory_price_change_events as changes')
            ->join('products', 'products.id', '=', 'changes.product_id')
            ->whereNull('products.deleted_at')
            ->where('changes.id', '>', $cursor)
            ->orderBy('changes.id')
            ->limit(min(max($limit, 1), 100))
            ->get([
                'changes.id', 'changes.product_id', 'products.name',
                'changes.old_price', 'changes.new_price', 'changes.created_at',
            ])->map(fn ($change) => [
                'id' => (int) $change->id,
                'product_id' => (int) $change->product_id,
                'name' => $change->name,
                'old_price' => $change->old_price,
                'new_price' => $change->new_price,
                'decreased' => $change->old_price !== null && $change->new_price !== null
                    && (float) $change->new_price < (float) $change->old_price,
                'changed_at' => $change->created_at,
            ])->all();
    }

    /**
     * Acknowledge only events presented to this user; never skip unseen rows.
     * The caller must ensure the user explicitly confirmed the popup.
     */
    public function acknowledge(User $user, int $lastSeenId): void
    {
        DB::transaction(function () use ($user, $lastSeenId): void {
            $current = (int) (DB::table('inventory_price_acknowledgments')
                ->where('user_id', $user->id)->lockForUpdate()
                ->value('last_event_id') ?? 0);

            $batch = $this->pending($user);
            if ($batch === [] || (int) end($batch)['id'] !== $lastSeenId || $lastSeenId <= $current) {
                throw new \InvalidArgumentException('Price-change acknowledgment is stale or incomplete.');
            }

            DB::table('inventory_price_acknowledgments')->updateOrInsert(
                ['user_id' => $user->id],
                ['last_event_id' => $lastSeenId, 'acknowledged_at' => now(), 'updated_at' => now(), 'created_at' => now()]
            );
        });
    }
}
