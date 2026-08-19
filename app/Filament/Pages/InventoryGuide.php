<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasModuleAccess;
use App\Models\InventoryLocation;
use App\Support\AdminModules;
use Filament\Pages\Page;

/**
 * The inventory module's counterpart to the receiving guide.
 *
 * Written because the questions people actually ask — "where is the main
 * warehouse", "why does scan-and-add do nothing" — are answered by knowing
 * which screen does which job, and that knowledge lived only in the heads of
 * whoever built it. A printed sheet goes stale in a drawer; this sits one click
 * from the screens it describes.
 */
class InventoryGuide extends Page
{
    use HasModuleAccess;

    protected static string $moduleSlug = 'inventory';
    protected static ?string $title = 'Inventory Guide';
    protected static ?string $navigationLabel = 'How It Works';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';
    protected static ?int $navigationSort = 99;

    public string $tab = 'start';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return AdminModules::navigationGroupFor('inventory');
    }

    public function getView(): string
    {
        return 'filament.pages.inventory-guide';
    }

    public function getSubheading(): ?string
    {
        return 'Which screen does which job — and what to do when one argues with you.';
    }

    public function setTab(string $tab): void
    {
        $this->tab = $tab;
    }

    /**
     * The guide's first instruction is "make sure a location exists", so it may
     * as well answer that for this install rather than describing how to check.
     * A guide that reads the system it documents is worth more than one that
     * asks the reader to go and look.
     *
     * @return array{count: int, hasStorage: bool, names: array<int, string>}
     */
    public function getLocationStatusProperty(): array
    {
        $names = InventoryLocation::where('status', 'active')
            ->orderBy('name')
            ->pluck('name')
            ->all();

        return [
            'count'      => count($names),
            'hasStorage' => InventoryLocation::where('status', 'active')
                ->where('type', 'main_storage')
                ->exists(),
            'names'      => $names,
        ];
    }
}
