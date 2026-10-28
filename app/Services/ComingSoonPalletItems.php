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
                $q->whereNull('lines.line_status')->orWhere('lines.line_status', '!=', 'received');
            })
            ->whereNotNull('lines.inventory_item_id')
            ->where('lines.case_count', '>', 0);

        if ($locations !== null) {
            $query->whereIn('lines.inventory_location_id', $locations);
        }

        return $query->orderByDesc('lines.id')->limit(25)
            ->get(['lines.id', 'products.name', 'products.image_path',
                'lines.case_count', 'pallets.name as pallet_name',
                'pallets.expected_delivery_date'])
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'name' => $row->name,
                'image' => $row->image_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($row->image_path) : null,
                'pallet' => $row->pallet_name,
                'cases' => (int) $row->case_count,
                'expected' => $row->expected_delivery_date,
            ])->all();
    }
}
