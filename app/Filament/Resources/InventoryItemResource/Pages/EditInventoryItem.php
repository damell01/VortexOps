<?php

namespace App\Filament\Resources\InventoryItemResource\Pages;

use App\Filament\Resources\InventoryItemResource;
use App\Models\InventoryItem;
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

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
