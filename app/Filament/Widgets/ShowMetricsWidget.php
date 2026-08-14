<?php

namespace App\Filament\Widgets;

use App\Models\Show;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;

/**
 * Headline numbers for a single show, above the pipeline on its detail page.
 *
 * A widget rather than a page-template override: ViewShow renders Filament's
 * default view-record template plus two widgets, so overriding the template
 * would mean reproducing the infolist and widget slots by hand.
 */
class ShowMetricsWidget extends Widget
{
    protected static ?int $sort = -30;

    protected int | string | array $columnSpan = 'full';

    protected string $view = 'filament.widgets.show-metrics';

    public ?Model $record = null;

    /** @return array<int, array<string, string>> */
    public function getStats(): array
    {
        /** @var Show|null $show */
        $show = $this->record;

        if (! $show) {
            return [];
        }

        $entry     = $show->streamerLogEntry;
        $itemCount = $show->orders()->count();
        $revenue   = (float) ($show->gross_revenue ?? 0);
        $cost      = (float) ($entry?->product_cost ?? 0);

        return [
            [
                'label' => 'Revenue',
                'value' => '$' . number_format($revenue, 2),
                'sub'   => 'Gross for this show',
                'icon'  => 'heroicon-o-banknotes',
                'tone'  => 'blue',
            ],
            [
                'label' => 'Product Cost',
                'value' => '$' . number_format($cost, 2),
                'sub'   => 'From the streamer log',
                'icon'  => 'heroicon-o-receipt-percent',
                'tone'  => 'purple',
            ],
            [
                'label' => 'Margin',
                'value' => '$' . number_format($revenue - $cost, 2),
                'sub'   => $revenue > 0
                    ? round((($revenue - $cost) / $revenue) * 100, 1) . '% of revenue'
                    : '—',
                'icon'  => 'heroicon-o-chart-bar',
                'tone'  => 'green',
            ],
            [
                'label' => 'Items Sold',
                'value' => number_format($itemCount),
                'sub'   => 'Orders on this show',
                'icon'  => 'heroicon-o-cube',
                'tone'  => 'amber',
            ],
        ];
    }
}
