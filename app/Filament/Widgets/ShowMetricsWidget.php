<?php

namespace App\Filament\Widgets;

use App\Models\Show;
use Carbon\Carbon;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;

/**
 * Headline numbers for a single show, above the pipeline on its detail page.
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

        $entry         = $show->streamerLogEntry;

        // Items, not rows. This counted order rows, so a buyer taking three of
        // one lot in a single order showed as one item sold. Whatnot's own
        // units_sold covers the case where the order rows have not been
        // imported yet, which is most shows for their first hours.
        $orderRows     = $show->orders()->count();
        $orderUnits    = (int) $show->orders()->sum('quantity');
        $itemCount     = $orderUnits > 0 ? $orderUnits : (int) ($show->units_sold ?? 0);
        $shipmentCount = $show->shipments()->count();
        $pendingCount  = $show->shipments()
            ->whereRaw("LOWER(COALESCE(status, '')) <> 'delivered'")
            ->count();
        $revenue       = (float) ($show->gross_revenue ?? 0);
        $cost          = (float) ($entry?->product_cost ?? 0);

        $analyticsSynced = $this->humanTime($show->getAttribute('last_analytics_synced_at'));
        $shipmentsSynced = $this->humanTime($show->getAttribute('last_shipments_synced_at'));

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
                'sub'   => $orderUnits > 0
                    ? number_format($orderRows) . ' ' . \Illuminate\Support\Str::plural('order', $orderRows)
                    : 'From Whatnot — orders not imported yet',
                'icon'  => 'heroicon-o-cube',
                'tone'  => 'amber',
            ],
            [
                // What went out without being sold. It is the other half of
                // what left the shelf, and the show had no figure for it —
                // which made a stock count that did not match sales look like
                // a mistake rather than a giveaway.
                'label' => 'Giveaways',
                'value' => number_format((int) ($show->giveaways_count ?? 0)),
                'sub'   => ((float) ($show->giveaway_spend ?? 0)) > 0
                    ? '$' . number_format((float) $show->giveaway_spend, 2) . ' spent'
                    : 'No giveaway spend recorded',
                'icon'  => 'heroicon-o-gift',
                'tone'  => 'violet',
            ],
            [
                'label' => 'Shipments',
                'value' => number_format($shipmentCount),
                'sub'   => $pendingCount > 0
                    ? number_format($pendingCount) . ' not delivered'
                    : ($shipmentCount > 0 ? 'All delivered' : 'No shipment rows yet'),
                'icon'  => 'heroicon-o-truck',
                'tone'  => $pendingCount > 0 ? 'amber' : 'green',
            ],
            [
                'label' => 'Whatnot Sync',
                'value' => $analyticsSynced,
                'sub'   => 'Analytics · Shipments ' . $shipmentsSynced,
                'icon'  => 'heroicon-o-arrow-path',
                'tone'  => 'blue',
            ],
        ];
    }

    private function humanTime(mixed $value): string
    {
        if (! $value) {
            return 'Never';
        }

        try {
            return Carbon::parse($value)->diffForHumans(short: true);
        } catch (\Throwable) {
            return 'Unknown';
        }
    }
}
