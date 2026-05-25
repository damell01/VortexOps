<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasModuleAccess;
use App\Filament\Resources\InventoryMovementResource\Pages;
use App\Models\InventoryMovement;
use App\Support\AdminModules;
use Filament\Actions\Action as TableAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Placeholder;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InventoryMovementResource extends Resource
{
    use HasModuleAccess;

    protected static string $moduleSlug = 'inventory';

    protected static ?string $model = InventoryMovement::class;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-arrow-path';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return AdminModules::navigationGroupFor('inventory');
    }

    public static function getNavigationSort(): ?int
    {
        return 4;
    }

    public static function getNavigationLabel(): string
    {
        return 'Movement Log';
    }

    public static function getModelLabel(): string
    {
        return 'Movement';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Movement Log';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['item.name', 'item.sku', 'reason'];
    }

    public static function getGlobalSearchResultTitle(\Illuminate\Database\Eloquent\Model $record): string
    {
        return ($record->item->name ?? 'Movement') . ' — ' . ($record->movement_type ?? '');
    }

    public static function getGlobalSearchResultDetails(\Illuminate\Database\Eloquent\Model $record): array
    {
        return array_filter([
            'Date' => $record->created_at?->format('M j, Y'),
            'Qty' => $record->quantity,
        ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['item', 'fromLocation', 'toLocation', 'createdByUser']);
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Movement Details')
                ->schema([
                    Grid::make(2)->schema([
                        Placeholder::make('created_at')
                            ->label('Date & Time')
                            ->content(fn (InventoryMovement $record): string => $record->created_at?->format('M j, Y g:i A') ?? '—'),
                        Placeholder::make('movement_type')
                            ->label('Movement Type')
                            ->content(fn (InventoryMovement $record): string => InventoryMovement::movementTypeLabels()[$record->movement_type] ?? $record->movement_type),
                        Placeholder::make('item')
                            ->label('Item')
                            ->content(fn (InventoryMovement $record): string => $record->item?->name ?? '—'),
                        Placeholder::make('sku')
                            ->label('SKU')
                            ->content(fn (InventoryMovement $record): string => $record->item?->sku ?? '—'),
                        Placeholder::make('quantity')
                            ->label('Quantity')
                            ->content(fn (InventoryMovement $record): string => number_format((float) $record->quantity, 0)),
                        Placeholder::make('created_by')
                            ->label('Created By')
                            ->content(fn (InventoryMovement $record): string => $record->createdByUser?->name ?? 'System'),
                        Placeholder::make('from_location')
                            ->label('From Location')
                            ->content(fn (InventoryMovement $record): string => $record->fromLocation?->name ?? '—'),
                        Placeholder::make('to_location')
                            ->label('To Location')
                            ->content(fn (InventoryMovement $record): string => $record->toLocation?->name ?? '—'),
                        Placeholder::make('reference')
                            ->label('Reference')
                            ->content(fn (InventoryMovement $record): string => $record->reference_type && $record->reference_id ? "{$record->reference_type} #{$record->reference_id}" : 'Manual update'),
                    ]),
                    Placeholder::make('reason')
                        ->label('Reason')
                        ->content(fn (InventoryMovement $record): string => $record->reason ?: '—'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Date & Time')
                    ->dateTime('M j, Y g:i A')
                    ->sortable(),
                TextColumn::make('item.name')
                    ->label('Item')
                    ->searchable(),
                TextColumn::make('item.sku')
                    ->label('SKU')
                    ->placeholder('—'),
                TextColumn::make('movement_type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => InventoryMovement::movementTypeLabels()[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'opening' => 'info',
                        'transfer' => 'primary',
                        'adjustment' => 'warning',
                        'sale_deduction' => 'success',
                        'return' => 'gray',
                        'damaged' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('quantity')
                    ->numeric(decimalPlaces: 0)
                    ->sortable(),
                TextColumn::make('fromLocation.name')
                    ->label('From')
                    ->placeholder('—'),
                TextColumn::make('toLocation.name')
                    ->label('To')
                    ->placeholder('—'),
                TextColumn::make('reason')
                    ->limit(50)
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('createdByUser.name')
                    ->label('By')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('movement_type')
                    ->options(InventoryMovement::movementTypeLabels()),
                SelectFilter::make('inventory_item_id')
                    ->label('Item')
                    ->relationship('item', 'name')
                    ->searchable(),
                SelectFilter::make('from_location_id')
                    ->label('From Location')
                    ->relationship('fromLocation', 'name'),
                SelectFilter::make('to_location_id')
                    ->label('To Location')
                    ->relationship('toLocation', 'name'),
            ])
            ->headerActions([
                TableAction::make('export_csv')
                    ->label('Export CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->url(fn () => route('export.movement-log'))
                    ->openUrlInNewTab(),
            ])
            ->actions([
                ViewAction::make(),
            ])
            ->striped()
            ->persistFiltersInSession()
            ->paginationPageOptions([10, 25, 50])
            ->defaultPaginationPageOption(25)
            ->deferLoading()
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInventoryMovements::route('/'),
            'view' => Pages\ViewInventoryMovement::route('/{record}'),
        ];
    }
}
