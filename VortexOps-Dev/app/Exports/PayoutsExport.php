<?php

namespace App\Exports;

use App\Models\Payout;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PayoutsExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    public function __construct(private Collection $payouts) {}

    public function collection(): Collection
    {
        return $this->payouts;
    }

    public function headings(): array
    {
        return [
            'Show Date', 'Show Title', 'Streamer', 'Payout Type',
            'Base Pay', 'Tips', 'Deductions', 'Net Pay',
            'Routing Bank Label', 'Status', 'Notes',
        ];
    }

    public function map($payout): array
    {
        return [
            $payout->show?->show_date?->format('Y-m-d'),
            $payout->show?->title,
            $payout->streamer?->name,
            $payout->payout_type,
            (float) $payout->base_pay,
            (float) $payout->tips_pay,
            (float) $payout->deductions_total,
            (float) $payout->net_pay,
            $payout->routing_bank_label,
            $payout->status,
            $payout->notes,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
