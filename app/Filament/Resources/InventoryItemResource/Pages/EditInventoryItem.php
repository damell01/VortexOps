<?php

namespace App\Filament\Resources\InventoryItemResource\Pages;

use App\Filament\Resources\InventoryItemResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditInventoryItem extends EditRecord
{
    protected static string $resource = InventoryItemResource::class;

    public function mount(string|int $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->record->load('stock.location');
        parent::mount($record);
    }

    public function getSubheading(): ?string
    {
        return 'Edit product details, barcode, case/container contents, sourcing, pricing, and reorder settings. Use Move or correct stock for quantity changes.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('view')
                ->label('View Item')
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->url(fn () => InventoryItemResource::getUrl('view', ['record' => $this->record])),

            Action::make('stock')
                ->label('Move / Correct Stock')
                ->icon('heroicon-o-arrows-right-left')
                ->color('warning')
                ->url(fn () => InventoryItemResource::getUrl('stock', ['record' => $this->record])),

            DeleteAction::make()
                ->visible(fn () => InventoryItemResource::canDelete($this->record))
                ->tooltip(fn () => InventoryItemResource::canDelete($this->record)
                    ? null
                    : 'This item still has stock on hand. Move or correct the stock to zero before deleting it.'),
        ];
    }
}
