<?php

namespace App\Filament\Resources\InventoryContainerResource\Pages;

use App\Filament\Pages\InventoryBreakdown;
use App\Filament\Pages\InventoryPutaway;
use App\Filament\Pages\InventoryReceiving;
use App\Filament\Resources\InventoryContainerResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListInventoryContainers extends ListRecords
{
    protected static string $resource = InventoryContainerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('receiving')
                ->label('Receiving')
                ->icon('heroicon-o-truck')
                ->color('gray')
                ->url(InventoryReceiving::getUrl()),
            Action::make('breakdown')
                ->label('Pallet Breakdown')
                ->icon('heroicon-o-squares-2x2')
                ->color('gray')
                ->url(InventoryBreakdown::getUrl()),
            Action::make('putaway')
                ->label('Container Putaway')
                ->icon('heroicon-o-map')
                ->color('gray')
                ->url(InventoryPutaway::getUrl()),
            CreateAction::make(),
        ];
    }
}
