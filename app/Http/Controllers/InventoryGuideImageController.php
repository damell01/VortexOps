<?php

namespace App\Http\Controllers;

use App\Filament\Pages\DashboardImproved;
use App\Filament\Pages\InventoryGuide;
use App\Filament\Pages\PalletReceivingHistory;
use App\Filament\Resources\InventoryItemResource;
use App\Filament\Resources\PalletResource;
use App\Filament\Resources\VendorResource;

class InventoryGuideImageController extends Controller
{
    public function __invoke(string $image)
    {
        $pages = [
            'dashboard.jpg'=>DashboardImproved::class,
            'receiving.jpg'=>PalletResource::class,
            'location-search.jpg'=>PalletResource::class,
            'partial-summary.jpg'=>PalletResource::class,
            'attachments.jpg'=>PalletResource::class,
            'history.jpg'=>PalletReceivingHistory::class,
            'vendor.jpg'=>VendorResource::class,
            'inventory.jpg'=>InventoryItemResource::class,
            'item-history.jpg'=>InventoryItemResource::class,
        ];
        abort_unless(isset($pages[$image]), 404);
        abort_unless(InventoryGuide::canAccess() && $pages[$image]::canAccess(), 403);
        $path = resource_path('guides/inventory-20261012/'.$image);
        abort_unless(is_file($path), 404);
        return response()->file($path, ['Content-Type'=>'image/jpeg', 'Cache-Control'=>'private, no-store']);
    }
}
