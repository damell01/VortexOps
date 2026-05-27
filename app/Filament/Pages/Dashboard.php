<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\ActiveStreamersWidget;
use App\Filament\Widgets\InventoryByLocationWidget;
use App\Filament\Widgets\InventoryOverviewWidget;
use App\Filament\Widgets\LowStockWidget;
use App\Filament\Widgets\RecentMovementsWidget;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    public function getColumns(): int | array
    {
        return [
            'md' => 6,
            'xl' => 12,
        ];
    }

    public function getWidgets(): array
    {
        return [
            InventoryOverviewWidget::class,
            InventoryByLocationWidget::class,
            LowStockWidget::class,
            RecentMovementsWidget::class,
            ActiveStreamersWidget::class,
        ];
    }
}
