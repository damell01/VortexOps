<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasAdminNavVisibility;
use App\Filament\Concerns\HasModuleAccess;
use App\Filament\Resources\WhatnotBuyerResource\Pages;
use App\Models\WhatnotBuyer;
use App\Support\AdminModules;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class WhatnotBuyerResource extends Resource
{
    use HasModuleAccess, HasAdminNavVisibility;

    protected static string $moduleSlug  = 'streams';
    protected static string $featureSlug = 'whatnot_buyers';

    protected static ?string $model          = WhatnotBuyer::class;
    protected static ?string $navigationLabel = 'Buyers';

    protected static ?string $navigationParentItem = 'Shows';
    protected static ?string $pluralLabel    = 'Buyers';

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-users';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return AdminModules::navigationGroupFor('streams');
    }

    public static function getNavigationSort(): ?int
    {
        return 37;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('username')
                    ->label('Username')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold'),

                TextColumn::make('display_name')
                    ->label('Display Name')
                    ->searchable()
                    ->placeholder('—'),

                TextColumn::make('total_orders')
                    ->label('Orders')
                    ->sortable()
                    ->alignRight(),

                TextColumn::make('lifetime_spend')
                    ->label('Lifetime Spend')
                    ->money('USD')
                    ->sortable()
                    ->alignRight(),

                TextColumn::make('avg_order_value')
                    ->label('Avg Order')
                    ->money('USD')
                    ->sortable()
                    ->alignRight()
                    ->placeholder('—'),

                TextColumn::make('first_purchase_date')
                    ->label('First Order')
                    ->date('M j, Y')
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('last_purchase_date')
                    ->label('Last Order')
                    ->date('M j, Y')
                    ->sortable()
                    ->placeholder('—'),
            ])
            ->defaultSort('lifetime_spend', 'desc')
            ->filters([
                Filter::make('repeat_buyers')
                    ->label('Repeat Buyers (2+ orders)')
                    ->query(fn (Builder $q) => $q->where('total_orders', '>=', 2)),

                Filter::make('high_value')
                    ->label('High Value ($100+ lifetime)')
                    ->query(fn (Builder $q) => $q->where('lifetime_spend', '>=', 100)),
            ])
            ->searchPlaceholder('Search by username or display name…');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWhatnotBuyers::route('/'),
        ];
    }
}
