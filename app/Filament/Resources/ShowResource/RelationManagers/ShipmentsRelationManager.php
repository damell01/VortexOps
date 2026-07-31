<?php

namespace App\Filament\Resources\ShowResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;

class ShipmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'shipments';
    protected static ?string $title = 'Shipments';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('tracking_number')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('buyer_username')
                    ->label('Buyer')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('tracking_number')
                    ->label('Tracking #')
                    ->copyable()
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('info'),

                TextColumn::make('carrier')
                    ->label('Carrier')
                    ->badge()
                    ->color('primary'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'Ready to Ship' => 'warning',
                        'Shipped' => 'info',
                        'Delivered' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => str_replace('_', ' ', ucwords($state))),

                TextColumn::make('item_count')
                    ->label('Items')
                    ->sortable(),

                TextColumn::make('weight_oz')
                    ->label('Weight (oz)')
                    ->numeric(decimals: 1)
                    ->sortable(),

                TextColumn::make('dimensions_json')
                    ->label('Dimensions')
                    ->formatStateUsing(function ($state) {
                        if (! $state) return '—';
                        $dims = $state;
                        return sprintf(
                            "%s × %s × %s in",
                            $dims['length_in'] ?? '?',
                            $dims['width_in'] ?? '?',
                            $dims['height_in'] ?? '?'
                        );
                    }),

                TextColumn::make('shipping_cost')
                    ->label('Shipping Cost')
                    ->money('USD')
                    ->sortable(),

                BadgeColumn::make('insurance_added')
                    ->label('Insurance')
                    ->trueIcon('heroicon-o-check-circle')
                    ->trueColor('success')
                    ->falseIcon('heroicon-o-x-circle')
                    ->falseColor('gray'),

                BadgeColumn::make('signature_required')
                    ->label('Signature')
                    ->trueIcon('heroicon-o-check-circle')
                    ->trueColor('success')
                    ->falseIcon('heroicon-o-x-circle')
                    ->falseColor('gray'),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M d, Y H:i')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                //
            ])
            ->actions([
                //
            ])
            ->bulkActions([
                //
            ])
            ->emptyStateHeading('No shipments yet')
            ->emptyStateDescription('Shipments will appear here after orders are imported from Whatnot.');
    }
}
