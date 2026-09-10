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
        // Load only what the PDF renders. Packing slips are not used here.
        $pallet->load(['vendor', 'lines.inventoryItem']);

        $reference = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) ($pallet->reference ?: 'no-reference'));
        $latestLineUpdate = $pallet->lines->max(fn ($line) => optional($line->updated_at)->timestamp ?? 0);
        $version = substr(sha1(implode('|', [
            $pallet->updated_at?->timestamp ?? 0,
            $latestLineUpdate,
            (string) ($pallet->shipping_cost ?? 0),
            (string) ($pallet->payment_fees ?? 0),
        ])), 0, 12);

        $relativePath = "receiving-reports/vortexops-pallet-{$pallet->id}-{$reference}-{$version}.pdf";

        // Reuse a previously rendered report until the pallet or its lines change.
        if (Storage::disk('public')->exists($relativePath)) {
            return $relativePath;
        }

        $generatedAt = now();
        $html = view('reports.pallet-receiving', [
            'pallet' => $pallet,
            'lines' => $this->getPalletLineDetails($pallet),
            'totals' => $this->calculatePalletTotals($pallet),
            'generatedAt' => $generatedAt,
        ])->render();

        // Branding is embedded, so DomPDF does not need remote fetching.
        $pdf = Pdf::loadHTML($html)->setPaper('a4')
            ->setOption('isRemoteEnabled', false)
            ->setOption('margin-top', 8)->setOption('margin-bottom', 8)
            ->setOption('margin-left', 8)->setOption('margin-right', 8);

        $path = Storage::disk('public')->path($relativePath);
        @mkdir(dirname($path), 0755, true);
        $pdf->save($path);

        return $relativePath;
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

    private function getPalletLineDetails(Pallet $pallet): array
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

    private function calculatePalletTotals(Pallet $pallet): array
    {
        return $this->calculateTotals($pallet);
    }

    private function calculateTotals(Pallet $pallet): array
    {
        $totalQty = 0.0;
        $merchandiseCost = 0.0;
        $packageQty = 0.0;
        $caseLines = 0;
        $singleLines = 0;

        foreach ($pallet->lines as $line) {
            $qty = $line->totalQuantityExpected();
            $totalQty += $qty;
            $merchandiseCost += $qty * (float) $line->unit_cost;
            $packageQty += (float) $line->case_count;

            if ((float) $line->quantity_per_case <= 1) $singleLines++;
            else $caseLines++;
        }

        $shipping = (float) ($pallet->shipping_cost ?? 0);
        $fees = (float) ($pallet->payment_fees ?? 0);
        $landedCost = $merchandiseCost + $shipping + $fees;

        return [
            'package_qty' => $packageQty,
            'qty' => $totalQty,
            'case_lines' => $caseLines,
            'single_lines' => $singleLines,
            'total_cost' => number_format($merchandiseCost, 2),
            'merchandise_cost' => number_format($merchandiseCost, 2),
            'shipping_cost' => number_format($shipping, 2),
            'payment_fees' => number_format($fees, 2),
            'landed_cost' => number_format($landedCost, 2),
            'avg_cost' => $totalQty > 0 ? number_format($landedCost / $totalQty, 2) : '0.00',
        ];
    }
}
