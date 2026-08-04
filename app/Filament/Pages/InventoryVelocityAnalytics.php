<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasModuleAccess;
use App\Services\InventoryVelocityService;
use App\Support\AdminModules;
use Filament\Pages\Page;

class InventoryVelocityAnalytics extends Page
{
    use HasModuleAccess;

    protected static string $moduleSlug = 'inventory';
    protected static ?string $title = 'Velocity Analytics';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return AdminModules::navigationGroupFor('inventory');
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-bolt';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function getNavigationLabel(): string
    {
        return 'Velocity Analytics';
    }

    public static function getNavigationSort(): ?int
    {
        return 999;
    }

    public function getView(): string
    {
        return 'filament.pages.inventory-velocity-analytics';
    }

    public function getSubheading(): ?string
    {
        return 'Identify fast movers, slow movers, and dead stock for optimization';
    }

    // ── Computed Properties ────────────────────────────────────────────────

    public function getSummaryProperty(): array
    {
        return app(InventoryVelocityService::class)->getSummary(30);
    }

    public function getFastMoversProperty(): array
    {
        return app(InventoryVelocityService::class)->getFastMovers(30, 10);
    }

    public function getSlowMoversProperty(): array
    {
        return app(InventoryVelocityService::class)->getSlowMovers(30, 10);
    }

    public function getDeadStockProperty(): array
    {
        return app(InventoryVelocityService::class)->getDeadStock(30, 10);
    }

    public function getHighValueFastMoversProperty(): array
    {
        return app(InventoryVelocityService::class)->getHighValueFastMovers(30, 10);
    }

    public function getAllRankedProperty(): array
    {
        return app(InventoryVelocityService::class)->getRankedByVelocity(30);
    }
}
