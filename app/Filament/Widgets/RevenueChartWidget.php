<?php

namespace App\Filament\Widgets;

use App\Models\Show;
use App\Support\AdminModules;
use Filament\Widgets\ChartWidget;
use Flowframe\Trend\Trend;

class RevenueChartWidget extends ChartWidget
{
    protected static bool $isLazy = true;
    protected static ?int $sort = 5;
    protected string $color     = 'success';
    protected ?string $maxHeight = '280px';
    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->isAdmin() && AdminModules::isEnabled('streams');
    }

    public function getHeading(): string
    {
        return 'Gross Revenue — Last 12 Weeks';
    }

    protected function getFilters(): ?array
    {
        return [
            'weeks'  => 'Last 12 weeks',
            'months' => 'Last 12 months',
        ];
    }

    protected function getData(): array
    {
        try {
            if (($this->filter ?? 'weeks') === 'months') {
                $trend = Trend::query(
                    Show::query()->whereNotIn('status', ['cancelled'])
                )
                    ->between(now()->subMonths(11)->startOfMonth(), now()->endOfMonth())
                    ->perMonth()
                    ->dateColumn('show_date')
                    ->sum('gross_revenue');

                $labels = $trend->map(fn ($t) => \Illuminate\Support\Carbon::parse($t->date)->format('M Y'))->toArray();
                $values = $trend->pluck('aggregate')->toArray();
            } else {
                $trend = Trend::query(
                    Show::query()->whereNotIn('status', ['cancelled'])
                )
                    ->between(now()->subWeeks(11)->startOfWeek(), now()->endOfWeek())
                    ->perWeek()
                    ->dateColumn('show_date')
                    ->sum('gross_revenue');

                $labels = $trend->map(fn ($t) => \Illuminate\Support\Carbon::parse($t->date)->format('M j'))->toArray();
                $values = $trend->pluck('aggregate')->toArray();
            }
        } catch (\Throwable) {
            $labels = [];
            $values = [];
        }

        return [
            'datasets' => [
                [
                    'label'           => 'Gross Revenue',
                    'data'            => $values,
                    'borderColor'     => '#10b981',
                    'backgroundColor' => '#10b98120',
                    'fill'            => true,
                    'tension'         => 0.4,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
