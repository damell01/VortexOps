<?php

namespace App\Filament\Resources\InventoryMovementResource\Pages;

use App\Filament\Resources\InventoryMovementResource;
use Filament\Resources\Pages\ListRecords;

class ListInventoryMovements extends ListRecords
{
    protected static string $resource = InventoryMovementResource::class;

    public function getSubheading(): ?string
    {
        return 'Every stock change — receipts, sales, adjustments — with who made it, when, and why.';
    }
}
