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
        $rows = InventoryItem::with(['stock.location'])
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        return $this->streamCsv('inventory-items', function () use ($rows) {
            $this->row(['SKU', 'Name', 'Category', 'Unit Cost', 'Reorder Level', 'Total Qty', 'Active', 'Notes']);

            foreach ($rows as $item) {
                $this->row([
                    $item->sku,
                    $item->name,
                    $item->category,
                    number_format((float) $item->unit_cost, 2),
                    $item->reorder_level ?? '',
                    number_format($item->totalQuantity(), 2),
                    $item->is_active ? 'Yes' : 'No',
                    $item->notes ?? '',
                ]);
            }
        });
    }

    public function stockLevels(): StreamedResponse
    {
        $rows = InventoryStock::with(['item', 'location'])
            ->join('inventory_items', 'inventory_stocks.inventory_item_id', '=', 'inventory_items.id')
            ->join('inventory_locations', 'inventory_stocks.inventory_location_id', '=', 'inventory_locations.id')
            ->orderBy('inventory_items.name')
            ->orderBy('inventory_locations.name')
            ->select('inventory_stocks.*')
            ->get();

        return $this->streamCsv('stock-levels', function () use ($rows) {
            $this->row(['Item', 'SKU', 'Category', 'Location', 'Location Type', 'Quantity', 'Unit Cost', 'Stock Value']);

            foreach ($rows as $stock) {
                $this->row([
                    $stock->item->name ?? '',
                    $stock->item->sku ?? '',
                    $stock->item->category ?? '',
                    $stock->location->name ?? '',
                    $stock->location->type ?? '',
                    number_format((float) $stock->quantity, 2),
                    number_format((float) ($stock->item->unit_cost ?? 0), 2),
                    number_format((float) $stock->quantity * (float) ($stock->item->unit_cost ?? 0), 2),
                ]);
            }
        });
    }

    public function movementLog(Request $request): StreamedResponse
    {
        $query = InventoryMovement::with(['item', 'fromLocation', 'toLocation', 'createdByUser'])
            ->latest()
            ->limit(10000);

        if ($request->filled('item_id')) {
            $query->where('inventory_item_id', $request->item_id);
        }
        if ($request->filled('type')) {
            $query->where('movement_type', $request->type);
        }

        $rows = $query->get();

        return $this->streamCsv('movement-log', function () use ($rows) {
            $this->row(['Date', 'Item', 'SKU', 'Type', 'Quantity', 'From Location', 'To Location', 'Reason', 'Created By']);

            foreach ($rows as $m) {
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
            }
        });
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private StreamedResponse $response;
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
