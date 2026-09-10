<?php

namespace App\Services;

use App\Models\Pallet;
use App\Models\ScannerReceivingSession;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class ReceivingReportService
{
    /** Legacy only. Receiving sessions are no longer surfaced in the UI. */
    public function generateSessionReport(ScannerReceivingSession $session): string
    {
        $session->load(['pallet.vendor', 'pallet.lines.inventoryItem', 'user']);

        $html = view('reports.receiving-session', [
            'session' => $session,
            'items' => $this->getSessionItems($session),
            'totals' => $this->calculateSessionTotals($session),
            'generatedAt' => now(),
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

        $html = view('reports.pallet-receiving', [
            'pallet' => $pallet,
            'lines' => $this->getPalletLineDetails($pallet, $costService),
            'totals' => $this->calculatePalletTotals($pallet, $costService),
            'generatedAt' => now(),
        ])->render();

        $pdf = Pdf::loadHTML($html)->setPaper('a4')
            ->setOption('isRemoteEnabled', true)
            ->setOption('margin-top', 8)->setOption('margin-bottom', 8)
            ->setOption('margin-left', 8)->setOption('margin-right', 8);

        $reference = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) ($pallet->reference ?: 'no-reference'));
        $filename = "vortexops-pallet-{$pallet->id}-{$reference}-" . date('Y-m-d-His') . '.pdf';
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
            $singleUnits = (float) $line->quantity_per_case <= 1;

            $lines[] = [
                'item_name' => $item?->name ?? ($line->description ?: $line->vendor_description ?: 'Unknown'),
                'sku' => $item?->sku ?? '—',
                'quantity_label' => $singleUnits ? 'Single Units' : 'Cases',
                'display_quantity' => (float) $line->case_count,
                'pack_size' => $singleUnits ? null : (float) $line->quantity_per_case,
                'total_units' => $qty,
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
        $packageQty = 0.0;
        $caseLines = 0;
        $singleLines = 0;

        foreach ($pallet->lines as $line) {
            $qty = $line->totalQuantityExpected();
            $totalQty += $qty;
            $totalCost += $qty * (float) $line->unit_cost;
            $packageQty += (float) $line->case_count;

            if ((float) $line->quantity_per_case <= 1) $singleLines++;
            else $caseLines++;
        }

        return [
            'package_qty' => $packageQty,
            'qty' => $totalQty,
            'case_lines' => $caseLines,
            'single_lines' => $singleLines,
            'total_cost' => number_format($totalCost, 2),
            'avg_cost' => $totalQty > 0 ? number_format($totalCost / $totalQty, 2) : '0.00',
        ];
    }
}
