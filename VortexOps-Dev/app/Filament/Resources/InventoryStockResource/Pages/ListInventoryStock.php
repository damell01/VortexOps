<?php

namespace App\Filament\Resources\InventoryStockResource\Pages;

use App\Filament\Resources\InventoryStockResource;
use Filament\Resources\Pages\ListRecords;

class ListInventoryStock extends ListRecords
{
    protected static string $resource = InventoryStockResource::class;

    public function getSubheading(): ?string
    {
        return 'Current on-hand quantity by item and location — one row per item/location combination.';
    }
}
