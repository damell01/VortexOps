<?php

namespace App\Filament\Resources\FulfillmentPackages\Tables;

use App\Models\FulfillmentPackage;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class FulfillmentPackagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tracking_number')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                BadgeColumn::make('status')
                    ->colors([
                        'gray' => 'pending',
                        'info' => 'shipped',
                        'success' => 'delivered',
                        'danger' => 'returned',
                    ])
                    ->sortable(),
                TextColumn::make('carrier')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('shipped_at')
                    ->dateTime('M d, Y H:i')
                    ->sortable(),
                TextColumn::make('items_count')
                    ->label('Items')
                    ->getStateUsing(fn (FulfillmentPackage $record) => $record->getItemCount())
                    ->sortable(),
                TextColumn::make('createdBy.name')
                    ->label('Created By')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime('M d, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'shipped' => 'Shipped',
                        'delivered' => 'Delivered',
                        'returned' => 'Returned',
                    ]),
                SelectFilter::make('carrier')
                    ->options([
                        'usps' => 'USPS',
                        'ups' => 'UPS',
                        'fedex' => 'FedEx',
                        'dhl' => 'DHL',
                        'other' => 'Other',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
