<?php

namespace App\Filament\Resources\FulfillmentResource\RelationManagers;

use App\Filament\Resources\FulfillmentResource;
use App\Models\WhatnotShowOrder;
use App\Support\NavVisibility;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

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

                // Populated by the shipments-tab scraper (whatnot:sync-shipments) —
                // hidden by default since most fulfillment work doesn't need it.
                TextColumn::make('shipment_weight_oz')
                    ->label('Weight')
                    ->formatStateUsing(fn ($state) => $state !== null ? number_format((float) $state, 1) . ' oz' : '—')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('box_dimensions')
                    ->label('Box')
                    ->state(fn (WhatnotShowOrder $record) => $record->box_length_in && $record->box_width_in && $record->box_height_in
                        ? sprintf('%s×%s×%s in', $record->box_length_in, $record->box_width_in, $record->box_height_in)
                        : null)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('shipping_carrier')
                    ->label('Carrier')
                    ->state(fn (WhatnotShowOrder $record) => trim("{$record->shipping_carrier} {$record->shipping_service}") ?: null)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('shipping_status')
                    ->options(WhatnotShowOrder::shippingStatusLabels()),
            ])
            ->emptyStateHeading('No items on this show yet')
            ->emptyStateIcon('heroicon-o-shopping-cart')
            ->paginated([25, 50, 100])
            ->bulkActions($locked ? [] : [
                BulkActionGroup::make(
                    collect(WhatnotShowOrder::shippingStatusLabels())
                        ->map(fn (string $label, string $status) => BulkAction::make("mark_{$status}")
                            ->label("Mark {$label}")
                            ->icon('heroicon-o-check')
                            ->action(function (Collection $records) use ($status, $label) {
                                $records->each->update(['shipping_status' => $status]);
                                Notification::make()
                                    ->title("{$records->count()} item(s) marked {$label}")
                                    ->success()
                                    ->send();
                            })
                            ->deselectRecordsAfterCompletion())
                        ->values()
                        ->all()
                ),
            ]);
    }
}
