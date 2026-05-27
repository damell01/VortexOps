<?php

namespace App\Filament\Pages;

use App\Filament\Resources\ReviewSessionResource;
use App\Filament\Resources\ShowResource;
use App\Filament\Widgets\ActiveStreamersWidget;
use App\Filament\Widgets\InventoryByLocationWidget;
use App\Filament\Widgets\InventoryOverviewWidget;
use App\Filament\Widgets\LowStockWidget;
use App\Filament\Widgets\RecentMovementsWidget;
use Filament\Actions\Action;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Contracts\Support\Htmlable;

class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'VortexOps Dashboard';

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

    protected function getHeaderActions(): array
    {
        return [
            Action::make('new_show')
                ->label('New Show')
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->url(ShowResource::getUrl('create')),
            Action::make('review_sessions')
                ->label('Review Sessions')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->color('gray')
                ->url(ReviewSessionResource::getUrl('index')),
        ];
    }

    public function getSubheading(): string | Htmlable | null
    {
        return 'Live operations view for inventory, shows, payouts, and review activity.';
    }
}
