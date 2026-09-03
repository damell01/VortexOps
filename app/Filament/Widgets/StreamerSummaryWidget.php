<?php

namespace App\Filament\Widgets;

use App\Models\Streamer;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;

class StreamerSummaryWidget extends Widget
{
    protected static ?int $sort = -30;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.streamer-summary';

    public ?Model $record = null;

    /** @return array<int, array<string, string>> */
    public function getStats(): array
    {
        /** @var Streamer|null $streamer */
        $streamer = $this->record;

        if (! $streamer) {
            return [];
        }

        $scorecard = $streamer->scorecard();
        $terms = match ($streamer->payout_type) {
            'profit_share' => rtrim(rtrim(number_format((float) $streamer->payout_percentage, 2), '0'), '.') . '% profit share',
            'hourly'       => '$' . number_format((float) $streamer->hourly_rate, 2) . '/hr',
            'package'      => '$' . number_format((float) $streamer->package_rate, 2) . '/package',
            'flat_rate'    => '$' . number_format((float) $streamer->flat_rate, 2) . ' flat',
            'hybrid'       => '$' . number_format((float) $streamer->hourly_rate, 2) . '/hr + variable',
            default        => Streamer::payoutTypeLabels()[$streamer->payout_type] ?? ucfirst((string) $streamer->payout_type),
        };

        $rating = $scorecard['avg_rating'] !== null
            ? number_format((float) $scorecard['avg_rating'], 2) . ' / 5'
            : '—';

        return [
            [
                'label' => 'Pay Structure',
                'value' => $terms,
                'sub'   => ucfirst(str_replace('_', ' ', (string) $streamer->payout_cadence)) . ' cadence',
                'icon'  => 'heroicon-o-banknotes',
                'tone'  => 'blue',
            ],
            [
                'label' => 'Shows',
                'value' => number_format((int) ($scorecard['shows'] ?? 0)),
                'sub'   => '$' . number_format((float) ($scorecard['gross'] ?? 0), 2) . ' gross driven',
                'icon'  => 'heroicon-o-video-camera',
                'tone'  => 'violet',
            ],
            [
                'label' => 'Margin Contributed',
                'value' => '$' . number_format((float) ($scorecard['margin'] ?? 0), 2),
                'sub'   => 'Across attributed shows',
                'icon'  => 'heroicon-o-chart-bar-square',
                'tone'  => 'green',
            ],
            [
                'label' => 'Average Rating',
                'value' => $rating,
                'sub'   => number_format((int) ($scorecard['rated_shows'] ?? 0)) . ' rated show(s)',
                'icon'  => 'heroicon-o-star',
                'tone'  => 'amber',
            ],
            [
                'label' => 'Inventory Pools',
                'value' => number_format($streamer->inventoryLocations()->where('status', 'active')->count()),
                'sub'   => 'Active assigned locations',
                'icon'  => 'heroicon-o-cube',
                'tone'  => 'blue',
            ],
        ];
    }
}
