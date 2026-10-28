<?php

namespace App\Services;

use App\Models\User;
use App\Support\InventoryVisibility;
use Illuminate\Support\Facades\DB;

class ComingSoonPalletItems
{
    public function items(User $user): array
    {
        $locations = InventoryVisibility::locationIdsFor($user);
        $query = DB::table('pallet_lines as lines')
            ->join('pallets', 'pallets.id', '=', 'lines.pallet_id')
            ->join('products', 'products.id', '=', 'lines.inventory_item_id')
            ->whereNull('pallets.deleted_at')
            ->whereNull('products.deleted_at')
            ->whereIn('pallets.stage', ['created', 'staged', 'receiving'])
            ->where(function ($q) {
                $q->whereNull('pallets.status')->orWhereNotIn('pallets.status', ['received', 'processed']);
            })
            ->where(function ($q) {
                $q->whereNull('lines.line_status')->orWhere('lines.line_status', '!=', 'received');
            })
            ->whereNotNull('lines.inventory_item_id')
            ->where('lines.case_count', '>', 0)
            ->whereNotNull('lines.inventory_location_id');

        if ($locations !== null) {
            $query->whereIn('lines.inventory_location_id', $locations);
        }

        return $query->leftJoin('inventory_pallet_review_states as reviewed', function ($join) use ($user) {
                $join->on('reviewed.pallet_line_id', '=', 'lines.id')->where('reviewed.user_id', '=', $user->id);
            })->orderByDesc('lines.id')->limit(100)
            ->get(['lines.id', 'products.name', 'products.image_path',
                'lines.case_count', 'lines.updated_at as line_updated_at', 'reviewed.fingerprint as reviewed_fingerprint', 'pallets.name as pallet_name',
                'pallets.expected_delivery_date'])
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'fingerprint' => hash('sha256', implode('|', [$row->name, $row->image_path, $row->case_count, $row->pallet_name, $row->expected_delivery_date])),
                'reviewed_fingerprint' => $row->reviewed_fingerprint,
                'name' => $row->name,
                'image' => $row->image_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($row->image_path) : null,
                'pallet' => $row->pallet_name,
                'cases' => (int) $row->case_count,
                'expected' => $row->expected_delivery_date,
            ])->filter(fn ($item) => $item['fingerprint'] !== $item['reviewed_fingerprint'])->values()->all();
    }

    public function acknowledge(User $user, array $items): void
    {
        $pending = collect($this->items($user))->keyBy('id');
        foreach ($items as $item) {
            $id = (int) ($item['id'] ?? 0);
            if (! isset($pending[$id]) || ($item['fingerprint'] ?? null) !== $pending[$id]['fingerprint']) {
                throw new \InvalidArgumentException('Pallet status changed; refresh before acknowledging.');
            }
            DB::table('inventory_pallet_review_states')->updateOrInsert(
                ['user_id' => $user->id, 'pallet_line_id' => $id],
                ['fingerprint' => $pending[$id]['fingerprint'], 'acknowledged_at' => now(), 'updated_at' => now(), 'created_at' => now()]
            );
        }
    }
}
