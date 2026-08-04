<?php

namespace App\Filament\Widgets;

use App\Models\InventorySnapshot;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Route;

class InventorySummaryWidget extends Widget
{
    protected static ?string $heading = 'Inventory Summary';

    protected static string $view = 'filament.widgets.inventory-summary-widget';

    protected static ?int $sort = 2;

    public function getInventoryData(): array
    {
        $latest = InventorySnapshot::latest('snapshot_date')->first();

        if (!$latest) {
            return [
                'total_value' => 0,
                'total_items' => 0,
                'total_quantity' => 0,
                'snapshot_date' => null,
                'previous_value' => null,
                'value_diff' => null,
                'value_diff_pct' => null,
                'locations' => [],
                'types' => [],
                'slow_moving_count' => 0,
                'stock_outs_count' => 0,
            ];
        }

        $previous = InventorySnapshot::where('snapshot_date', '<', $latest->snapshot_date)
            ->latest('snapshot_date')
            ->first();

        $valueDiff = null;
        $valueDiffPct = null;
        if ($previous) {
            $valueDiff = $latest->total_value - $previous->total_value;
            $valueDiffPct = $previous->total_value > 0
                ? (($valueDiff / $previous->total_value) * 100)
                : 0;
        }

        return [
            'total_value' => (float) $latest->total_value,
            'total_items' => $latest->total_items,
            'total_quantity' => $latest->total_quantity,
            'snapshot_date' => $latest->snapshot_date,
            'previous_value' => $previous?->total_value,
            'value_diff' => $valueDiff,
            'value_diff_pct' => $valueDiffPct,
            'locations' => $latest->location_breakdown ?? [],
            'types' => $latest->item_type_breakdown ?? [],
            'slow_moving_count' => count($latest->slow_moving_items ?? []),
            'stock_outs_count' => count($latest->stock_outs ?? []),
        ];
    }

    public function getReportUrl(): string
    {
        return route('filament.admin.pages.inventory-report');
    }
}
