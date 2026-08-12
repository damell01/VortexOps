<?php

namespace App\Filament\Resources\InventoryLocationResource\Pages;

use App\Filament\Resources\InventoryLocationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListInventoryLocations extends ListRecords
{
    protected static string $resource = InventoryLocationResource::class;

    public function getSubheading(): ?string
    {
        return 'Physical or virtual storage locations stock is tracked against — a shelf, a bin, a channel\'s staging area.';
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
