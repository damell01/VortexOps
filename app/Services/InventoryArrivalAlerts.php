<?php

namespace App\Services;

use App\Models\User;
use App\Support\InventoryVisibility;
use Illuminate\Support\Facades\DB;

class InventoryArrivalAlerts
{
    public function pending(User $user): array
    {
        $ids = InventoryVisibility::locationIdsFor($user);
        $query = DB::table('inventory_arrival_events as events')
            ->join('products', 'products.id', '=', 'events.product_id')
            ->leftJoin('inventory_arrival_reviews as reviews', function ($join) use ($user) {
                $join->on('reviews.event_id', '=', 'events.id')->where('reviews.user_id', '=', $user->id);
            })
            ->whereNull('products.deleted_at')->whereNull('reviews.id');
        if ($ids !== null) $query->whereIn('events.location_id', $ids);
        return $query->orderBy('events.id')->limit(100)
            ->get(['events.id', 'events.kind', 'products.name', 'products.image_path', 'events.quantity_after'])
            ->map(fn ($e) => [
                'id' => (int) $e->id, 'kind' => $e->kind, 'name' => $e->name,
                'image' => $e->image_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($e->image_path) : null,
                'quantity' => $e->quantity_after,
            ])->all();
    }

    public function acknowledge(User $user, array $items): void
    {
        $valid = array_column($this->pending($user), 'id');
        foreach ($items as $item) {
            $id = (int) ($item['id'] ?? 0);
            if (! in_array($id, $valid, true)) throw new \InvalidArgumentException('Inventory arrival changed.');
            DB::table('inventory_arrival_reviews')->insertOrIgnore([
                'user_id' => $user->id, 'event_id' => $id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }
}
