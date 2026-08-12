<?php

namespace App\Filament\Resources\InventoryItemResource\RelationManagers;

use App\Models\ProductIdentity;
use App\Models\Vendor;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * A SKU can carry several scannable codes — e.g. different vendor packaging
 * on a restock ships with a different UPC than the original case. Lookups
 * already fall through to product_identities (Product::findByScan()); this
 * is where those extra codes actually get registered.
 */
class BarcodesRelationManager extends RelationManager
{
    protected static string $relationship = 'identities';

    protected static ?string $title = 'Barcodes';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(2)->schema([
                Select::make('type')
                    ->label('Type')
                    ->options([
                        ProductIdentity::TYPE_BARCODE => 'Barcode',
                        ProductIdentity::TYPE_UPC     => 'UPC',
                    ])
                    ->default(ProductIdentity::TYPE_BARCODE)
                    ->required(),

                TextInput::make('value')
                    ->label('Code')
                    ->required()
                    ->maxLength(255),

                Select::make('vendor_id')
                    ->label('Vendor (optional)')
                    ->relationship('vendor', 'name')
                    ->searchable()
                    ->preload()
                    ->helperText('Only set this if the code is specific to one vendor\'s packaging.')
                    ->columnSpanFull(),
            ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->whereIn('type', [
                ProductIdentity::TYPE_BARCODE,
                ProductIdentity::TYPE_UPC,
            ]))
            ->columns([
                TextColumn::make('value')
                    ->label('Code')
                    ->searchable()
                    ->weight('semibold'),

                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        ProductIdentity::TYPE_BARCODE => 'Barcode',
                        ProductIdentity::TYPE_UPC     => 'UPC',
                        default                        => $state,
                    }),

                TextColumn::make('vendor.name')
                    ->label('Vendor')
                    ->placeholder('Any'),

                TextColumn::make('created_at')
                    ->label('Added')
                    ->dateTime('M j, Y')
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('No extra barcodes yet')
            ->emptyStateDescription('This item\'s primary barcode/SKU/UPC already works for scanning. Add extras here when a restock ships with different packaging under a different code.')
            ->defaultSort('created_at', 'desc');
    }
}
