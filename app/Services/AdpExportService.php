<?php

namespace App\Services;

use App\Models\WeeklyPayoutBatch;

class AdpExportService
{
    /**
     * Generate an ADP-compatible CSV for a finalized pay run batch.
     *
     * Columns follow ADP Workforce Now payroll import format:
     *   Co Code, Batch ID, File #, Line #, Code, Amount
     *
     * Streamers without an ADP employee ID are still included but their
     * File # column will be blank so the admin can fill it in before upload.
     */
    public function exportBatchCsv(WeeklyPayoutBatch $batch): string
    {
        $companyCode   = config('adp.company_code', 'VB');
        $earningsCode  = config('adp.earnings_code', 'REG');
        $batchId       = $batch->week_start->format('Ymd');

        $payouts = $batch->payouts()->with('streamer')->get();

        $rows = [];
        $rows[] = ['Co Code', 'Batch ID', 'File #', 'Line #', 'Earnings Code', 'Amount', 'Streamer Name', 'Legal Name', 'Bank Label', 'Week Of'];

        foreach ($payouts as $index => $payout) {
            $streamer = $payout->streamer;
            $rows[] = [
                $companyCode,
                $batchId,
                $streamer?->adp_employee_id ?? '',
                $index + 1,
                $earningsCode,
                number_format((float) $payout->calculated_payout, 2, '.', ''),
                $streamer?->name ?? '(unknown)',
                $streamer?->legal_name ?? '',
                $payout->routing_bank_label ?? '',
                $batch->week_start->format('m/d/Y') . ' – ' . $batch->week_end->format('m/d/Y'),
            ];
        }

        $handle = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    public function exportFilename(WeeklyPayoutBatch $batch): string
    {
        return 'adp-payroll-' . $batch->week_start->format('Y-m-d') . '.csv';
    }
}
