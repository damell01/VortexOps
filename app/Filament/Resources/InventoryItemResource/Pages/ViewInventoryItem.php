<?php

namespace App\Filament\Resources\InventoryItemResource\Pages;

use App\Filament\Resources\InventoryItemResource;
use App\Models\InventoryLocation;
use App\Services\InventoryService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewInventoryItem extends ViewRecord
{
    protected static string $resource = InventoryItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
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
                    $this->refreshFormData(['stock']);
                }),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Details')->columns(3)->schema([
                TextEntry::make('sku')->label('SKU')->placeholder('—')->copyable(),
                TextEntry::make('barcode')->placeholder('—')->copyable(),
                TextEntry::make('category')->badge()->placeholder('—'),
                TextEntry::make('unit_cost')->label('List Cost')->money('USD'),
                TextEntry::make('average_cost')->label('Avg Cost (WAC)')->money('USD'),
                TextEntry::make('total_units_received')->label('Total Received')->numeric(),
                TextEntry::make('reorder_level')->label('Reorder At')->placeholder('—'),
                TextEntry::make('is_active')->label('Active')->badge()
                    ->formatStateUsing(fn ($state) => $state ? 'Active' : 'Inactive')
                    ->color(fn ($state) => $state ? 'success' : 'gray'),
                TextEntry::make('preferredVendor.name')->label('Preferred Vendor')->placeholder('—'),
            ]),

            Section::make('Stock by Location')->schema([
                ViewEntry::make('stock_by_location')
                    ->label('')
                    ->view('filament.infolists.stock-by-location'),
            ]),

            Section::make('Notes')->schema([
                TextEntry::make('description')->placeholder('—')->columnSpanFull(),
                TextEntry::make('notes')->placeholder('—')->columnSpanFull(),
            ])->collapsed(),
        ]);
    }
}
