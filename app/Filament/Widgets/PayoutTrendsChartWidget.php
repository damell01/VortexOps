<?php

namespace App\Filament\Widgets;

use App\Models\Payout;
use App\Support\AdminModules;
use Filament\Widgets\ChartWidget;
use Flowframe\Trend\Trend;

class PayoutTrendsChartWidget extends ChartWidget
{
    protected static ?int $sort = 6;
    protected string $color     = 'info';
    protected ?string $maxHeight = '280px';
    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->isAdmin() && AdminModules::isEnabled('payouts');
    }

    public function getHeading(): string
    {
        return 'Weekly Payout Totals — Last 12 Weeks';
    }

    protected function getData(): array
    {
        try {
            $trend = Trend::query(
                Payout::query()->whereIn('status', ['approved', 'paid'])
            )
                ->between(now()->subWeeks(11)->startOfWeek(), now()->endOfWeek())
                ->perWeek()
                ->dateColumn('created_at')
                ->sum('calculated_payout');

            $data   = $trend->pluck('aggregate')->toArray();
            $labels = $trend->map(fn ($t) => \Illuminate\Support\Carbon::parse($t->date)->format('M j'))->toArray();
        } catch (\Throwable) {
            $data   = [];
            $labels = [];
        }

        return [
            'datasets' => [
                [
                    'label'           => 'Payouts',
                    'data'            => $data,
                    'borderColor'     => '#3b82f6',
                    'backgroundColor' => '#3b82f620',
                    'fill'            => true,
                    'tension'         => 0.4,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
