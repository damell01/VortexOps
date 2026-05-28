<?php

namespace App\Filament\Resources\InventoryItemResource\Pages;

use App\Filament\Resources\InventoryItemResource;
use App\Filament\Resources\InventoryContainerResource;
use App\Models\InventoryLocation;
use App\Services\InventoryService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewInventoryItem extends ViewRecord
{
    protected static string $resource = InventoryItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            Action::make('new_container')
                ->label('Add Container')
                ->icon('heroicon-o-cube-transparent')
                ->color('info')
                ->url(fn (): string => InventoryContainerResource::getUrl('create', [
                    'inventory_item_id' => $this->record->getKey(),
                ])),
            Action::make('add_stock')
                ->label('Add Stock')
                ->icon('heroicon-o-plus-circle')
                ->color('success')
                ->form([
                    Select::make('location_id')
                        ->label('Location')
                        ->options(fn () => InventoryLocation::where('status', 'active')->pluck('name', 'id'))
                        ->required()
                        ->searchable(),
                    TextInput::make('quantity')
                        ->numeric()
                        ->required()
                        ->minValue(0.01),
                    Select::make('movement_type')
                        ->options(['opening' => 'Opening Stock', 'adjustment' => 'Adjustment', 'return' => 'Return'])
                        ->default('opening')
                        ->required(),
                    Textarea::make('reason')->rows(2),
                ])
                ->action(function (array $data): void {
                    $location = InventoryLocation::findOrFail($data['location_id']);
                    app(InventoryService::class)->addStock(
                        $this->record,
                        $location,
                        (float) $data['quantity'],
                        $data['movement_type'],
                        $data['reason'] ?? null
                    );
                    Notification::make()->title('Stock added')->success()->send();
                }),
        ];
    }
}
