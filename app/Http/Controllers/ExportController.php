<?php

namespace App\Http\Controllers;

use App\Exports\PayoutsExport;
use App\Exports\ShowsExport;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\Payout;
use App\Models\Show;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function inventoryItems(): StreamedResponse
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

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
        abort_unless(auth()->user()?->isAdmin(), 403);

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
        abort_unless(auth()->user()?->isAdmin(), 403);

        return $this->streamCsv('movement-log', function () use ($request) {
            $this->row(['Date', 'Item', 'SKU', 'Type', 'Quantity', 'From Location', 'To Location', 'Reason', 'Created By']);

            $query = InventoryMovement::with(['item', 'fromLocation', 'toLocation', 'createdByUser'])->latest();

            if ($request->filled('item_id')) {
                $query->where('inventory_item_id', $request->item_id);
            }
            if ($request->filled('type')) {
                $query->where('movement_type', $request->type);
            }

            $query->limit(50000)->lazy(500)->each(function (InventoryMovement $m) {
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

    public function shows(): mixed
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
        $shows = Show::with(['channel'])->orderBy('show_date', 'desc')->get();
        return Excel::download(new ShowsExport($shows), 'shows-' . now()->format('Y-m-d') . '.xlsx');
    }

    public function payouts(): mixed
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
        $payouts = Payout::with(['show', 'streamer'])->orderBy('created_at', 'desc')->get();
        return Excel::download(new PayoutsExport($payouts), 'payouts-' . now()->format('Y-m-d') . '.xlsx');
    }

    public function payoutPdf(Payout $payout): mixed
    {
        $user = auth()->user();
        if (! $user?->isAdmin()) {
            $streamerId = $user?->streamer?->id;
            abort_unless($streamerId && $payout->streamer_id === $streamerId, 403);
        }
        $payout->loadMissing(['show', 'streamer']);
        $pdf = Pdf::loadView('pdf.payout-statement', compact('payout'));
        $slug = str($payout->streamer?->name ?? 'payout')->slug();
        $date = $payout->show?->show_date?->format('Y-m-d') ?? now()->format('Y-m-d');
        return $pdf->download("payout-{$slug}-{$date}.pdf");
    }

    public function manifestTemplate(): StreamedResponse
    {
        return $this->streamCsv('pallet-manifest-template', function () {
            $this->row(['description', 'sku', 'barcode', 'case_count', 'unit_cost', 'notes']);
            $this->row(['2024 Topps Chrome Hobby Box', 'TOP-CHR-24', '012345678901', '10', '89.99', '']);
            $this->row(['2024 Bowman Jumbo Case', 'BOW-JMB-24', '', '5', '249.00', 'Handle with care']);
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
