<?php

namespace App\Filament\Resources\InventoryItemResource\Pages;

use App\Filament\Resources\InventoryItemResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListInventoryItems extends ListRecords
{
    protected static string $resource = InventoryItemResource::class;

    public function getSubheading(): ?string
    {
        return 'The product catalogue — SKUs, barcodes, reorder levels, and current stock. Cost updates automatically (weighted average) every time you receive inventory.';
    }

    protected function getHeaderActions(): array
    {
        $user = auth()->user();
        $canCreate = $user && ($user->isAdmin() || $user->isOwner() || $user->isStreamer());

        return [
            CreateAction::make()
                ->visible($canCreate),
        ];
    }
}
