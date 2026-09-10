<?php

namespace App\Services;

use App\Models\Pallet;
use App\Models\ScannerReceivingSession;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class ReceivingReportService
{
    public function generateSessionReport(ScannerReceivingSession $session): string
    {
        $session->load(['pallet.vendor', 'pallet.lines.inventoryItem', 'user']);

        $html = view('reports.receiving-session', [
            'session' => $session,
            'items' => $this->getSessionItems($session),
            'totals' => $this->calculateSessionTotals($session),
        ])->render();

        $pdf = Pdf::loadHTML($html)->setPaper('a4')
            ->setOption('margin-top', 10)->setOption('margin-bottom', 10)
            ->setOption('margin-left', 10)->setOption('margin-right', 10);

        $filename = "session-{$session->id}-" . date('Y-m-d-His') . '.pdf';
        $path = Storage::disk('public')->path("receiving-reports/{$filename}");
        @mkdir(dirname($path), 0755, true);
        $pdf->save($path);

        return "receiving-reports/{$filename}";
    }

    public function generatePalletReport(Pallet $pallet): string
    {
        $pallet->load(['vendor', 'lines.inventoryItem', 'packingSlips']);
        $costService = app(InventoryCostService::class);
        $logoPath = public_path('images/vb-logo.svg');
        $logoData = is_file($logoPath)
            ? 'data:image/svg+xml;base64,' . base64_encode(file_get_contents($logoPath))
            : null;

        $html = view('reports.pallet-receiving', [
            'pallet' => $pallet,
            'lines' => $this->getPalletLineDetails($pallet, $costService),
            'totals' => $this->calculatePalletTotals($pallet, $costService),
            'logoData' => $logoData,
            'generatedAt' => now(),
        ])->render();

        $pdf = Pdf::loadHTML($html)->setPaper('a4')
            ->setOption('isRemoteEnabled', true)
            ->setOption('margin-top', 8)->setOption('margin-bottom', 8)
            ->setOption('margin-left', 8)->setOption('margin-right', 8);

        $filename = "pallet-{$pallet->id}-{$pallet->reference}-" . date('Y-m-d-His') . '.pdf';
        $path = Storage::disk('public')->path("receiving-reports/{$filename}");
        @mkdir(dirname($path), 0755, true);
        $pdf->save($path);

        return "receiving-reports/{$filename}";
    }

    private function getSessionItems(ScannerReceivingSession $session): array
    {
        $items = [];
        foreach ($session->pallet->lines as $line) {
            $qty = $line->totalQuantityExpected();
            $items[] = [
                'name' => $line->inventoryItem?->name ?? 'Unknown',
                'sku' => $line->inventoryItem?->sku ?? '—',
                'cases' => (int) $line->case_count,
                'qty' => $qty,
                'unit_cost' => number_format((float) $line->unit_cost, 2),
                'total_cost' => number_format($qty * (float) $line->unit_cost, 2),
            ];
        }
        return $items;
    }

    private function getPalletLineDetails(Pallet $pallet, InventoryCostService $costService): array
    {
        $lines = [];
        foreach ($pallet->lines as $line) {
            $item = $line->inventoryItem;
            $qty = $line->totalQuantityExpected();
            $lines[] = [
                'item_name' => $item?->name ?? ($line->description ?: $line->vendor_description ?: 'Unknown'),
                'sku' => $item?->sku ?? '—',
                'cases' => (int) $line->case_count,
                'qty_per_case' => (float) $line->quantity_per_case,
                'qty' => $qty,
                'unit_cost' => number_format((float) $line->unit_cost, 2),
                'total_cost' => number_format($qty * (float) $line->unit_cost, 2),
                'current_avg' => $item ? number_format((float) $item->average_cost, 2) : '—',
            ];
        }
        return $lines;
    }

    private function calculateSessionTotals(ScannerReceivingSession $session): array
    {
        return $this->calculateTotals($session->pallet);
    }

    private function calculatePalletTotals(Pallet $pallet, InventoryCostService $costService): array
    {
        return $this->calculateTotals($pallet);
    }

    private function calculateTotals(Pallet $pallet): array
    {
        $totalQty = 0.0;
        $totalCost = 0.0;
        $totalCases = 0;

        foreach ($pallet->lines as $line) {
            $qty = $line->totalQuantityExpected();
            $totalQty += $qty;
            $totalCost += $qty * (float) $line->unit_cost;
            $totalCases += (int) $line->case_count;
        }

        return [
            'cases' => $totalCases,
            'qty' => $totalQty,
            'total_cost' => number_format($totalCost, 2),
            'avg_cost' => $totalQty > 0 ? number_format($totalCost / $totalQty, 2) : '0.00',
        ];
    }
}
