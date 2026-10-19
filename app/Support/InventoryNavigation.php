<?php

namespace App\Support;

use App\Filament\Pages;
use App\Filament\Resources;

/** Secondary inventory destinations, shared by desktop and mobile page navigation. */
class InventoryNavigation
{
    public static function sections(): array
    {
        return [
            'overview' => [
                [Pages\InventoryOverview::class, 'Overview', null],
                [Pages\StockStatus::class, 'Stock status', null],
                [Pages\InventoryAge::class, 'Inventory age', null],
                [Pages\InventoryHealth::class, 'Inventory health', null],
            ],
            'catalog' => [
                [Resources\InventoryItemResource::class, 'All inventory', 'index'],
                [Resources\InventoryItemResource::class, 'Quick add stock', 'quick-add'],
            ],
            'receiving' => [
                [Resources\PalletResource::class, 'Receive inventory', 'index'],
                [Pages\InventoryScanner::class, 'Scan inventory', null],
            ],
            'history' => [
                [Pages\InventoryActivity::class, 'Recent activity', null],
                [Resources\InventoryMovementResource::class, 'Inventory log', 'index'],
            ],
            'setup' => [
                [Resources\InventoryLocationResource::class, 'Locations', 'index'],
                [Resources\VendorResource::class, 'Vendors', 'index'],
            ],
        ];
    }

    public static function linksForPage(array $scopes): array
    {
        foreach (self::sections() as $rows) {
            $matches = collect($rows)->contains(fn ($row) => collect($scopes)->contains(
                fn ($scope) => is_string($scope) && ($scope === $row[0] || str_starts_with($scope, $row[0] . '\\Pages\\')),
            ));
            if (!$matches) continue;

            return collect($rows)->filter(fn ($row) => $row[0]::canAccess()
                && !NavVisibility::isHiddenForUser($row[0], auth()->user()))
                ->map(fn ($row) => [
                    'label' => $row[1],
                    'url' => $row[2] === null ? $row[0]::getUrl(panel: 'admin') : $row[0]::getUrl($row[2], panel: 'admin'),
                ])->values()->all();
        }

        return [];
    }
}
