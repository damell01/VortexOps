<?php

namespace App\Filament\Widgets;

use App\Models\InventoryValueSnapshot;
use App\Models\Show;
use App\Support\AdminModules;
use App\Support\ChannelContext;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class InventoryValueVsSalesWidget extends ChartWidget
{
    protected static bool $isLazy = true;
    protected static ?int $sort = 8;
    protected ?string $maxHeight = '280px';
    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->isAdmin() && AdminModules::isEnabled('inventory');
    }

    public function getHeading(): string
    {
        return 'Avg. Inventory Value vs. Revenue — Last 6 Months';
    }

    public function getDescription(): ?string
    {
        return 'Average inventory value (WAC) is the mean of each day\'s end-of-day snapshot within the month. Requires inventory:snapshot-value to have run — a month with no snapshots yet shows $0.';
    }

    protected function getData(): array
    {
        $channelId = ChannelContext::currentId();

        $months = collect(range(5, 0))->map(fn ($i) => now()->subMonths($i)->startOfMonth());

        $avgValues = $months->map(fn (Carbon $month) => InventoryValueSnapshot::averageValueForMonth($month, $channelId));

        $revenue = $months->map(function (Carbon $month) {
            return (float) Show::whereNotIn('status', ['cancelled'])
                ->inChannelContext()
                ->whereBetween('show_date', [$month->toDateString(), $month->copy()->endOfMonth()->toDateString()])
                ->sum('whatnot_net');
        });

        return [
            'datasets' => [
                [
                    'label'           => 'Avg. Inventory Value',
                    'data'            => $avgValues->toArray(),
                    'borderColor'     => '#f59e0b',
                    'backgroundColor' => '#f59e0b20',
                    'fill'            => true,
                    'tension'         => 0.4,
                ],
                [
                    'label'           => 'Revenue (Net)',
                    'data'            => $revenue->toArray(),
                    'borderColor'     => '#10b981',
                    'backgroundColor' => '#10b98120',
                    'fill'            => true,
                    'tension'         => 0.4,
                ],
            ],
            'labels' => $months->map(fn (Carbon $m) => $m->format('M Y'))->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
