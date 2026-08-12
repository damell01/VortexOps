<?php

namespace App\Services;

use App\Models\Pallet;
use App\Models\ScannerReceivingSession;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class ReceivingReportService
{
    /**
     * Generate PDF report for a receiving session.
     */
    public function generateSessionReport(ScannerReceivingSession $session): string
    {
        $session->load([
            'pallet.vendor',
            'pallet.lines.inventoryItem',
            'user',
        ]);

        $html = view('reports.receiving-session', [
            'session' => $session,
            'items' => $this->getSessionItems($session),
            'totals' => $this->calculateSessionTotals($session),
        ])->render();

        $pdf = Pdf::loadHTML($html)
            ->setPaper('a4')
            ->setOption('margin-top', 10)
            ->setOption('margin-bottom', 10)
            ->setOption('margin-left', 10)
            ->setOption('margin-right', 10);

        $filename = "session-{$session->id}-" . date('Y-m-d-His') . '.pdf';
        $path = Storage::disk('public')->path("receiving-reports/{$filename}");
        @mkdir(dirname($path), 0755, true);
        $pdf->save($path);

        return "receiving-reports/{$filename}";
    }

    /**
     * Generate PDF report for a completed pallet receive.
     */
    public function generatePalletReport(Pallet $pallet): string
    {
        $pallet->load([
            'vendor',
            'lines.inventoryItem',
            'packingSlips',
            'scannerSessions.user',
        ]);

        $costService = app(InventoryCostService::class);

        $html = view('reports.pallet-receiving', [
            'pallet' => $pallet,
            'lines' => $this->getPalletLineDetails($pallet, $costService),
            'totals' => $this->calculatePalletTotals($pallet, $costService),
            'sessions' => $pallet->scannerSessions,
        ])->render();

        $pdf = Pdf::loadHTML($html)
            ->setPaper('a4')
            ->setOption('margin-top', 10)
            ->setOption('margin-bottom', 10)
            ->setOption('margin-left', 10)
            ->setOption('margin-right', 10);

        $filename = "pallet-{$pallet->id}-{$pallet->reference}-" . date('Y-m-d-His') . '.pdf';
        $path = Storage::disk('public')->path("receiving-reports/{$filename}");
        @mkdir(dirname($path), 0755, true);
        $pdf->save($path);

        return "receiving-reports/{$filename}";
    }

    /**
     * Get structured items from a session.
     */
    private function getSessionItems(ScannerReceivingSession $session): array
    {
        $items = [];

        foreach ($session->pallet->lines as $line) {
            $items[] = [
                'name'       => $line->inventoryItem?->name ?? 'Unknown',
                'sku'        => $line->inventoryItem?->sku ?? '—',
                'cases'      => (int) $line->case_count,
                'qty'        => (float) $line->quantity,
                'unit_cost'  => number_format((float) $line->unit_cost, 2),
                'total_cost' => number_format((float) $line->quantity * (float) $line->unit_cost, 2),
            ];
        }

        return $items;
    }

    /**
     * Get detailed line information for pallet report.
     */
    private function getPalletLineDetails(Pallet $pallet, InventoryCostService $costService): array
    {
        $lines = [];

        foreach ($pallet->lines as $line) {
            $item = $line->inventoryItem;
            $prevCost = $line->unit_cost; // Cost before this receipt

            $lines[] = [
                'item_name'      => $item?->name ?? 'Unknown',
                'sku'            => $item?->sku ?? '—',
                'cases'          => (int) $line->case_count,
                'qty'            => (float) $line->quantity,
                'unit_cost'      => number_format((float) $line->unit_cost, 2),
                'total_cost'     => number_format((float) $line->quantity * (float) $line->unit_cost, 2),
                'confidence'     => round((float) $line->match_confidence * 100) . '%',
                'status'         => $line->status ?? 'received',
                'current_avg'    => $item ? number_format((float) $item->average_cost, 2) : '—',
            ];
        }

        return $lines;
    }

    /**
     * Calculate totals for session.
     */
    private function calculateSessionTotals(ScannerReceivingSession $session): array
    {
        $totalQty = 0;
        $totalCost = 0;
        $totalCases = 0;

        foreach ($session->pallet->lines as $line) {
            $totalQty += (float) $line->quantity;
            $totalCost += (float) $line->quantity * (float) $line->unit_cost;
            $totalCases += (int) $line->case_count;
        }

        return [
            'cases'      => $totalCases,
            'qty'        => (float) $totalQty,
            'total_cost' => number_format($totalCost, 2),
            'avg_cost'   => $totalQty > 0 ? number_format($totalCost / $totalQty, 2) : '0.00',
        ];
    }

    /**
     * Calculate totals for pallet.
     */
    private function calculatePalletTotals(Pallet $pallet, InventoryCostService $costService): array
    {
        $totalQty = 0;
        $totalCost = 0;
        $totalCases = 0;

        foreach ($pallet->lines as $line) {
            $totalQty += (float) $line->quantity;
            $totalCost += (float) $line->quantity * (float) $line->unit_cost;
            $totalCases += (int) $line->case_count;
        }

        return [
            'cases'      => $totalCases,
            'qty'        => (float) $totalQty,
            'total_cost' => number_format($totalCost, 2),
            'avg_cost'   => $totalQty > 0 ? number_format($totalCost / $totalQty, 2) : '0.00',
        ];
    }
}
