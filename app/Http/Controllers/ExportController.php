<?php

namespace App\Http\Controllers;

use App\Exports\PayoutsExport;
use App\Exports\ShowsExport;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
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

    public function inventoryPdf(Request $request)
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        $items = InventoryItem::with('stock.location')
            ->where('is_active', true)
            ->orderBy('sku')
            ->get();

        $data = [
            'items' => $items,
            'exportDate' => now()->format('M d, Y'),
            'exportTime' => now()->format('h:i A'),
            'totalItems' => $items->count(),
            'totalValue' => $items->sum(fn ($item) =>
                ($item->stock->sum('quantity') ?? 0) * ($item->average_cost ?? 0)
            ),
        ];

        $pdf = Pdf::loadView('exports.inventory-report', $data)
            ->setPaper('a4')
            ->setOption('margin-top', 10)
            ->setOption('margin-bottom', 10)
            ->setOption('margin-left', 10)
            ->setOption('margin-right', 10);

        $filename = 'inventory-report-' . now()->format('Y-m-d-His') . '.pdf';

        // If download=1 is in query, force download; otherwise stream in browser
        if ($request->query('download')) {
            return $pdf->download($filename);
        }

        return $pdf->stream($filename);
    }

    /**
     * The inventory handbook as a printable PDF.
     *
     * Screenshots are passed as base64 data URIs rather than paths. dompdf
     * resolves a relative src against its chroot and a file:// path against
     * the filesystem, and which of those works depends on configuration this
     * install does not pin — so the images are put beyond doubt by embedding
     * them. A missing picture in a printed manual is not recoverable by the
     * reader the way a broken <img> on a page is.
     */
    public function inventoryManualPdf(Request $request)
    {
        // The printed copy is filtered the same way the screen is: whoever
        // pressed the button gets the handbook for the app they have.
        $sections = \App\Support\HandbookVisibility::sections(\App\Support\InventoryManual::sections());

        $images = [];
        $widths = [];

        // The printable text column is 178mm wide and about 257mm tall. dompdf
        // does not move an image that overflows the page — it clips it — so a
        // tall screenshot has to be narrowed until it fits rather than left to
        // be cut in half at the foot of a page.
        $maxWidthMm  = 166;
        $maxHeightMm = 148;

        foreach ($sections as $section) {
            foreach ($section['steps'] as $step) {
                // A long form is captured in parts, so a step can carry more
                // than one picture.
                $shots = array_filter(array_merge([$step['shot'] ?? null], $step['more'] ?? []));

                foreach ($shots as $shot) {
                    if (isset($images[$shot])) {
                        continue;
                    }

                    $path = public_path(\App\Support\InventoryManual::IMAGE_DIR . '/' . $shot);

                    if (! is_file($path)) {
                        continue;
                    }

                    $images[$shot] = 'data:image/png;base64,' . base64_encode(file_get_contents($path));

                    [$w, $h] = getimagesize($path) ?: [0, 0];

                    $widths[$shot] = ($w > 0 && $h > 0)
                        ? round(min($maxWidthMm, $maxHeightMm * $w / $h), 1)
                        : $maxWidthMm;
                }
            }
        }

        $pdf = Pdf::loadView('exports.inventory-manual', [
            'title'           => \App\Support\InventoryManual::title(),
            'subtitle'        => \App\Support\InventoryManual::subtitle(),
            'brand'           => \App\Models\Setting::get('brand_name', 'VortexOps'),
            'sections'        => $sections,
            'troubleshooting' => \App\Support\HandbookVisibility::troubleshooting(\App\Support\InventoryManual::troubleshooting()),
            'screenIndex'     => \App\Support\HandbookVisibility::screens(\App\Support\InventoryManual::screenIndex()),
            'images'          => $images,
            'imageWidths'     => $widths,
            'generatedAt'     => now()->format('M j, Y'),
        ])->setPaper('a4');

        $filename = 'inventory-handbook-' . now()->format('Y-m-d') . '.pdf';

        return $request->query('download')
            ? $pdf->download($filename)
            : $pdf->stream($filename);
    }

    public function stockLevels(): StreamedResponse
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        return $this->streamCsv('stock-levels', function () {
            $this->row(['Item', 'SKU', 'Category', 'Location', 'Location Type', 'Quantity', 'Avg Cost', 'Stock Value']);

            InventoryStock::with(['item', 'location'])
                ->join('products', 'inventory_stock.inventory_item_id', '=', 'products.id')
                ->join('inventory_locations', 'inventory_stock.inventory_location_id', '=', 'inventory_locations.id')
                ->orderBy('products.name')
                ->orderBy('inventory_locations.name')
                ->select('inventory_stock.*')
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

    public function locations(): StreamedResponse
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        return $this->streamCsv('locations', function () {
            $this->row(['Name', 'Type', 'Streamer', 'Channel', 'Status', 'SKUs Stocked', 'Notes']);

            InventoryLocation::with(['streamer', 'channel'])
                ->withCount('stock')
                ->orderBy('name')
                ->lazy(500)
                ->each(function (InventoryLocation $location) {
                    $this->row([
                        $location->name,
                        InventoryLocation::typeLabels()[$location->type] ?? $location->type,
                        $location->streamer->name ?? '',
                        $location->channel->name ?? '',
                        InventoryLocation::statusLabels()[$location->status] ?? $location->status,
                        $location->stock_count,
                        $location->notes ?? '',
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

    public function inventoryAnalyticsPdf(Request $request)
    {
        $user = auth()->user();
        abort_unless(
            $user?->isAdmin() || $user?->isOwner() || $user?->isStreamer(),
            403
        );

        $page = app(\App\Filament\Pages\InventoryAnalytics::class);

        $data = [
            'title' => 'Inventory Analytics Summary',
            'date' => now()->format('F j, Y'),
            'time' => now()->format('H:i'),
            'summary' => $page->getSummary(),
            'lowStock' => $page->getLowStockItems(),
            'topVendors' => $page->getTopVendors(),
            'locations' => $page->getLocationHealth(),
            'fastMovers' => $page->getFastMovers(),
            'deadStock' => $page->getDeadStock(),
        ];

        $pdf = Pdf::loadView('filament.pages.inventory-analytics-pdf', $data)
            ->setPaper('a4', 'landscape')
            ->setOption('enable-local-file-access', true)
            ->setOption('margin-top', 10)
            ->setOption('margin-bottom', 10)
            ->setOption('margin-left', 5)
            ->setOption('margin-right', 5);

        $filename = 'analytics-summary-' . now()->format('Y-m-d-His') . '.pdf';

        if ($request->query('download')) {
            return $pdf->download($filename);
        }

        return $pdf->stream($filename);
    }

    public function showPlPdf(Show $show): mixed
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        $show->loadMissing([
            'streamers',
            'channel',
            'payouts.streamer',
            'latestDeductionRequest.lines.inventoryItem',
            'latestDeductionRequest.lines.location',
        ]);

        $pdf  = Pdf::loadView('pdf.show-pl', compact('show'))->setPaper('a4', 'portrait');
        $slug = str($show->title ?? 'show-' . $show->id)->slug();
        $date = $show->show_date?->format('Y-m-d') ?? now()->format('Y-m-d');

        return $pdf->download("show-pl-{$slug}-{$date}.pdf");
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
