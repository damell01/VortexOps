<?php

namespace App\Filament\Resources\InventoryItemResource\Pages;

use App\Filament\Resources\InventoryItemResource;
use Filament\Actions\Action;
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
        return [
            Action::make('quick-add')
                ->label('⚡ Quick Add')
                ->icon('heroicon-o-lightning-bolt')
                ->color('success')
                ->url(fn () => InventoryItemResource::getUrl('quick-add'))
                ->visible(function () {
                    $user = auth()->user();
                    return ($user?->isAdmin() ?? false) || ($user?->isOwner() ?? false) || ($user?->isStreamer() ?? false);
                }),
            CreateAction::make()
                ->label('+ Full Form')
                ->color('gray')
                ->visible(function () {
                    $user = auth()->user();
                    return ($user?->isAdmin() ?? false) || ($user?->isOwner() ?? false) || ($user?->isStreamer() ?? false);
                })
        ];
    }

}
