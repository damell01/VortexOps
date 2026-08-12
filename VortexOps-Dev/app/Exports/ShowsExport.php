<?php

namespace App\Exports;

use App\Models\Show;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ShowsExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    public function __construct(private Collection $shows) {}

    public function collection(): Collection
    {
        return $this->shows;
    }

    public function headings(): array
    {
        return [
            'Date', 'Title', 'Channel', 'Status',
            'Gross Revenue', 'Whatnot Net', 'Tips',
            'Units Sold', 'Buyers', 'Avg Order Value',
            'Total Views', 'Max Concurrent Viewers',
            'Avg Order Rating', 'Completed Earnings',
            'Giveaway Spend', 'Giveaways', 'Shares',
            'First-Time Buyers', 'Returning Buyers',
            'Show Duration (min)', 'Import Source',
        ];
    }

    public function map($show): array
    {
        return [
            $show->show_date?->format('Y-m-d'),
            $show->title,
            $show->channel?->name,
            $show->status,
            (float) $show->gross_revenue,
            (float) $show->whatnot_net,
            (float) $show->tips,
            $show->units_sold,
            $show->buyers_count,
            (float) $show->avg_order_value,
            $show->total_views,
            $show->max_concurrent_viewers,
            (float) $show->avg_order_rating,
            (float) $show->completed_earnings,
            (float) $show->giveaway_spend,
            $show->giveaways_count,
            $show->shares_count,
            $show->first_time_buyers,
            $show->returning_buyers,
            $show->show_duration,
            $show->import_source,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
