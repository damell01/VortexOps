<?php

namespace App\Filament\Resources\FulfillmentResource\RelationManagers;

use App\Filament\Resources\FulfillmentResource;
use App\Models\WhatnotShowOrder;
use App\Support\NavVisibility;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Sold items for this show, scoped to what fulfillment actually needs — item,
 * buyer, location, shipping status, and tracking. Deliberately excludes cost
 * and sale-price fields, which stay on the admin/streamer-facing order views.
 */
class FulfillmentOrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'orders';
    protected static ?string $title = 'Items to Fulfill';

    private function readOnly(): bool
    {
        $user = auth()->user();

        return ! ($user?->isAdmin() || $user?->isOwner())
            && NavVisibility::isReadOnlyForUser(FulfillmentResource::class, $user);
    }

    public function table(Table $table): Table
    {
        $locked = $this->readOnly();

        return $table
            ->recordTitleAttribute('item_name')
            ->defaultSort('lot_number')
            ->columns([
                TextColumn::make('lot_number')->label('Lot #')->width('72px')->placeholder('—'),
                TextColumn::make('item_name')->label('Item')->wrap()->placeholder('—'),
                TextColumn::make('buyer_display_name')
                    ->label('Buyer')
                    ->placeholder(fn (WhatnotShowOrder $record) => $record->buyer_username ?? '—'),
                TextColumn::make('quantity')->label('Qty')->numeric(),
                TextColumn::make('inventoryLocation.name')->label('Ship From')->placeholder('—'),

                SelectColumn::make('shipping_status')
                    ->label('Shipping Status')
                    ->options(WhatnotShowOrder::shippingStatusLabels())
                    ->selectablePlaceholder('— set status —')
                    ->disabled($locked),

                TextInputColumn::make('tracking_number')
                    ->label('Tracking #')
                    ->placeholder('—')
                    ->disabled($locked),
            ])
            ->filters([
                SelectFilter::make('shipping_status')
                    ->options(WhatnotShowOrder::shippingStatusLabels()),
            ])
            ->emptyStateHeading('No items on this show yet')
            ->emptyStateIcon('heroicon-o-shopping-cart')
            ->paginated([25, 50, 100]);
    }
}
