<?php

namespace App\Filament\Resources\FulfillmentPackages\RelationManagers;

use App\Models\InventoryItem;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('barcode')
                    ->label('Barcode or SKU')
                    ->placeholder('Scan barcode or enter SKU...')
                    ->hint('Type or scan to auto-fill product')
                    ->live(onBlur: true)
                    ->dehydrated(false)
                    ->afterStateUpdated(fn (callable $set) => $this->lookupProductByBarcode($set)),
                Select::make('product_id')
                    ->label('Product')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload()
                    ->optionsQuery(fn (Builder $query) => $query->orderBy('name'))
                    ->required(),
                TextInput::make('quantity')
                    ->label('Quantity')
                    ->required()
                    ->numeric()
                    ->minValue(1)
                    ->default(1),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('product')
            ->columns([
                TextColumn::make('product.name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('product.sku')
                    ->label('SKU')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('quantity')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('product.lastCost')
                    ->label('Unit Price')
                    ->money('USD')
                    ->sortable(),
                TextColumn::make('item_value')
                    ->label('Total Value')
                    ->getStateUsing(fn ($record) => ($record->quantity ?? 0) * ($record->product?->lastCost ?? 0))
                    ->money('USD'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add Item')
                    ->icon('heroicon-o-plus'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    protected function lookupProductByBarcode(callable $set): void
    {
        $barcode = $this->data['barcode'] ?? null;
        if (! $barcode) {
            return;
        }

        $product = InventoryItem::where('barcode', $barcode)
            ->orWhere('sku', $barcode)
            ->first();

        if ($product) {
            $set('product_id', $product->id);
            Notification::make()
                ->title('Product found')
                ->body("Added {$product->name}")
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('Product not found')
                ->body("No product found with barcode/SKU: {$barcode}")
                ->warning()
                ->send();
        }
    }
}
