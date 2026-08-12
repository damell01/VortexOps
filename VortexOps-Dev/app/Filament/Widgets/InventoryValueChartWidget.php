<?php

namespace App\Filament\Widgets;

use App\Models\InventoryMovement;
use App\Support\AdminModules;
use Filament\Widgets\ChartWidget;
use Flowframe\Trend\Trend;

class InventoryValueChartWidget extends ChartWidget
{
    protected static bool $isLazy = true;
    protected static ?int $sort = 7;
    protected string $color     = 'warning';
    protected ?string $maxHeight = '280px';
    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->isAdmin() && AdminModules::isEnabled('inventory');
    }

    public function getHeading(): string
    {
        return 'Inventory Movement Value — Last 12 Weeks';
    }

    protected function getFilters(): ?array
    {
        return [
            'opening'        => 'Openings / Received',
            'sale_deduction' => 'Sales Deductions',
            'adjustment'     => 'Adjustments',
        ];
    }

    protected function getData(): array
    {
        $type = $this->filter ?? 'opening';

        $colorMap = [
            'opening'        => '#f59e0b',
            'sale_deduction' => '#ef4444',
            'adjustment'     => '#8b5cf6',
        ];
        $labelMap = [
            'opening'        => 'Units Received',
            'sale_deduction' => 'Units Deducted (Sales)',
            'adjustment'     => 'Units Adjusted',
        ];

        try {
            $trend = Trend::query(
                InventoryMovement::query()->where('movement_type', $type)->inChannelContext()
            )
                ->between(now()->subWeeks(11)->startOfWeek(), now()->endOfWeek())
                ->perWeek()
                ->dateColumn('created_at')
                ->sum('quantity');

            $data   = $trend->pluck('aggregate')->toArray();
            $labels = $trend->map(fn ($t) => \Illuminate\Support\Carbon::parse($t->date)->format('M j'))->toArray();
        } catch (\Throwable) {
            $data   = [];
            $labels = [];
        }

        $color = $colorMap[$type] ?? '#f59e0b';

        return [
            'datasets' => [
                [
                    'label'           => $labelMap[$type] ?? 'Units',
                    'data'            => $data,
                    'borderColor'     => $color,
                    'backgroundColor' => $color . '20',
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
