<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasModuleAccess;
use App\Filament\Resources\InventoryContainerResource\Pages;
use App\Models\InventoryContainer;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Support\AdminModules;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InventoryContainerResource extends Resource
{
    use HasModuleAccess;

    protected static string $moduleSlug = 'inventory';

    protected static ?string $model = InventoryContainer::class;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-cube-transparent';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return AdminModules::navigationGroupFor('inventory');
    }

    public static function getNavigationSort(): ?int
    {
        return 3;
    }

    public static function getNavigationLabel(): string
    {
        return 'Container Tracking';
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['label', 'barcode'];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Container Details')->schema([
                Grid::make(2)->schema([
                    Select::make('inventory_item_id')
                        ->label('Inventory Item')
                        ->options(fn (): array => InventoryItem::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->default(fn () => request()->integer('inventory_item_id') ?: null)
                        ->required(),
                    Select::make('container_type')
                        ->options(InventoryContainer::typeLabels())
                        ->required()
                        ->default('case'),
                    TextInput::make('label')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('barcode')
                        ->maxLength(255)
                        ->unique(ignoreRecord: true),
                    TextInput::make('quantity')
                        ->numeric()
                        ->minValue(0)
                        ->required()
                        ->default(0),
                    Select::make('inventory_location_id')
                        ->label('Current Location')
                        ->options(fn (): array => InventoryLocation::activeOptions())
                        ->searchable()
                        ->nullable(),
                    Select::make('parent_container_id')
                        ->label('Parent Container')
                        ->options(fn (): array => InventoryContainer::query()->orderBy('label')->pluck('label', 'id')->all())
                        ->searchable()
                        ->nullable(),
                    Select::make('status')
                        ->options(InventoryContainer::statusLabels())
                        ->default('active')
                        ->required(),
                    Toggle::make('scanner_ready')
                        ->label('Scanner Ready')
                        ->default(false),
                ]),
                Textarea::make('notes')
                    ->rows(3)
                    ->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => InventoryContainer::schemaReady()
                ? InventoryContainer::query()->with([
                    'item:id,name,sku',
                    'location:id,name',
                    'parentContainer:id,label',
                ])
                : InventoryItem::query()->whereRaw('1 = 0'))
            ->columns([
                TextColumn::make('label')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold')
                    ->description(fn (InventoryContainer $record): ?string => $record->barcode ?: null),
                TextColumn::make('container_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => InventoryContainer::typeLabels()[$state] ?? ucfirst($state)),
                TextColumn::make('item.name')
                    ->label('Item')
                    ->searchable()
                    ->description(fn (InventoryContainer $record): ?string => $record->item?->sku),
                TextColumn::make('quantity')
                    ->numeric(decimalPlaces: 0)
                    ->sortable(),
                TextColumn::make('parentContainer.label')
                    ->label('Parent')
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('location.name')
                    ->label('Location')
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => InventoryContainer::statusLabels()[$state] ?? ucfirst($state)),
                IconColumn::make('scanner_ready')
                    ->label('Scanner')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('container_type')
                    ->options(InventoryContainer::typeLabels()),
                SelectFilter::make('status')
                    ->options(InventoryContainer::statusLabels()),
                SelectFilter::make('inventory_location_id')
                    ->label('Location')
                    ->options(fn (): array => InventoryLocation::activeOptions()),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->striped()
            ->persistFiltersInSession()
            ->paginationPageOptions([10, 25, 50])
            ->defaultPaginationPageOption(25)
            ->deferLoading()
            ->defaultSort('label');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInventoryContainers::route('/'),
            'create' => Pages\CreateInventoryContainer::route('/create'),
            'view' => Pages\ViewInventoryContainer::route('/{record}'),
            'edit' => Pages\EditInventoryContainer::route('/{record}/edit'),
        ];
    }
}
