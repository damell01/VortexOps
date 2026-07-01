<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function inventoryItems(): StreamedResponse
    {
        return $this->streamCsv('inventory-items', function () {
            $this->row(['SKU', 'Name', 'Category', 'List Cost', 'Avg Cost', 'Units Received', 'Reorder Level', 'Total Qty', 'Active', 'Notes']);

            InventoryItem::with(['stock'])
                ->orderBy('category')
                ->orderBy('name')
                ->lazy(500)
                ->each(function (InventoryItem $item) {
                    $this->row([
                        $item->sku,
                        $item->name,
                        $item->category,
                        number_format((float) $item->unit_cost, 2),
                        number_format((float) $item->average_cost, 4),
                        number_format((float) $item->total_units_received, 2),
                        $item->reorder_level ?? '',
                        number_format($item->totalQuantity(), 2),
                        $item->is_active ? 'Yes' : 'No',
                        $item->notes ?? '',
                    ]);
                });
        });
    }

    public function stockLevels(): StreamedResponse
    {
        return $this->streamCsv('stock-levels', function () {
            $this->row(['Item', 'SKU', 'Category', 'Location', 'Location Type', 'Quantity', 'Avg Cost', 'Stock Value']);

            InventoryStock::with(['item', 'location'])
                ->join('inventory_items', 'inventory_stocks.inventory_item_id', '=', 'inventory_items.id')
                ->join('inventory_locations', 'inventory_stocks.inventory_location_id', '=', 'inventory_locations.id')
                ->orderBy('inventory_items.name')
                ->orderBy('inventory_locations.name')
                ->select('inventory_stocks.*')
                ->lazy(500)
                ->each(function (InventoryStock $stock) {
                    $avgCost = (float) ($stock->item->average_cost > 0 ? $stock->item->average_cost : $stock->item->unit_cost ?? 0);
                    $this->row([
                        $stock->item->name ?? '',
                        $stock->item->sku ?? '',
                        $stock->item->category ?? '',
                        $stock->location->name ?? '',
                        $stock->location->type ?? '',
                        number_format((float) $stock->quantity, 2),
                        number_format($avgCost, 4),
                        number_format((float) $stock->quantity * $avgCost, 2),
                    ]);
                });
        });
    }

    public function movementLog(Request $request): StreamedResponse
    {
        return $this->streamCsv('movement-log', function () use ($request) {
            $this->row(['Date', 'Item', 'SKU', 'Type', 'Quantity', 'From Location', 'To Location', 'Reason', 'Created By']);

            $query = InventoryMovement::with(['item', 'fromLocation', 'toLocation', 'createdByUser'])->latest();

            if ($request->filled('item_id')) {
                $query->where('inventory_item_id', $request->item_id);
            }
            if ($request->filled('type')) {
                $query->where('movement_type', $request->type);
            }

            $query->lazy(500)->each(function (InventoryMovement $m) {
                $this->row([
                    $m->created_at->format('Y-m-d H:i'),
                    $m->item->name ?? '',
                    $m->item->sku ?? '',
                    $m->movement_type,
                    number_format((float) $m->quantity, 2),
                    $m->fromLocation->name ?? '',
                    $m->toLocation->name ?? '',
                    $m->reason ?? '',
                    $m->createdByUser->name ?? '',
                ]);
            });
        });
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private mixed $handle;

    private function streamCsv(string $filename, callable $writer): StreamedResponse
    {
        $date = now()->format('Y-m-d');

        return response()->streamDownload(function () use ($writer) {
            $this->handle = fopen('php://output', 'w');
            $writer();
            fclose($this->handle);
        }, "{$filename}-{$date}.csv", [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function row(array $cols): void
    {
        fputcsv($this->handle, $cols);
    }
}
