<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasModuleAccess;
use App\Models\InventoryItem;
use App\Models\PalletLineCost;
use App\Support\AdminModules;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class CostAdjustmentHistory extends Page implements HasTable
{
    use HasModuleAccess, InteractsWithTable;

    protected static string $moduleSlug = 'inventory';
    protected static ?string $title = 'Cost Adjustment History';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return AdminModules::navigationGroupFor('inventory');
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-arrow-trending-up';
    }

    public static function getNavigationLabel(): string
    {
        return 'Cost History';
    }

    public static function getNavigationSort(): ?int
    {
        return 5;
    }

    public function getView(): string
    {
        return 'filament.pages.cost-adjustment-history';
    }

    public function getSubheading(): ?string
    {
        return 'Complete audit trail of all cost adjustments with user attribution and change details';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(PalletLineCost::query()->with(['inventoryItem', 'palletLine', 'updatedBy']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('M d, Y H:i')
                    ->sortable(),

                TextColumn::make('inventoryItem.name')
                    ->label('Item')
                    ->searchable(['inventory_items.name', 'inventory_items.sku'])
                    ->description(fn (PalletLineCost $record) => $record->inventoryItem?->sku),

                TextColumn::make('palletLine.pallet.reference')
                    ->label('PO / Reference')
                    ->searchable(['pallets.reference'])
                    ->url(fn (PalletLineCost $record) => $record->palletLine?->pallet?->id
                        ? route('filament.admin.resources.pallets.view', ['record' => $record->palletLine->pallet->id])
                        : null),

                TextColumn::make('previous_unit_cost')
                    ->label('Previous Cost')
                    ->money('USD', locale: 'en_US')
                    ->alignRight()
                    ->default('—'),

                TextColumn::make('unit_cost')
                    ->label('New Cost')
                    ->money('USD', locale: 'en_US')
                    ->alignRight()
                    ->color(fn (PalletLineCost $record) => $record->unit_cost > $record->previous_unit_cost ? 'danger' : 'success'),

                TextColumn::make('variance')
                    ->label('Change')
                    ->alignRight()
                    ->state(function (PalletLineCost $record) {
                        if (!$record->previous_unit_cost) {
                            return '—';
                        }
                        $diff = $record->unit_cost - $record->previous_unit_cost;
                        $pct = ($diff / $record->previous_unit_cost) * 100;
                        return sprintf('%+.2f (%.1f%%)', $diff, $pct);
                    })
                    ->color(fn (PalletLineCost $record) => match (true) {
                        $record->unit_cost > $record->previous_unit_cost => 'danger',
                        $record->unit_cost < $record->previous_unit_cost => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('updatedBy.name')
                    ->label('Changed By')
                    ->searchable(['users.name', 'users.email']),

                TextColumn::make('notes')
                    ->label('Notes')
                    ->limit(50)
                    ->expandableLabelFalse(),
            ])
            ->filters([
                // Filters can be added here
            ])
            ->actions([
                // Actions can be added here
            ])
            ->bulkActions([
                // Bulk actions can be added here
            ]);
    }
}
