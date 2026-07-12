<?php

namespace App\Filament\Resources\StreamerLogResource\RelationManagers;

use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\WhatnotShowOrder;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Table;

/**
 * The items sold on the log entry's show, editable straight from the Streamer
 * Log page — so a streamer maps each item to inventory and confirms its cost
 * without opening the individual show. Items are imported automatically; the
 * streamer only enriches (map + cost), so there is no create/delete here.
 */
class ItemsSoldRelationManager extends RelationManager
{
    protected static string $relationship = 'showOrders';
    protected static ?string $title = 'Items Sold';

    /** Heading that doubles as a mapping-progress indicator (e.g. "Items Sold — 12 of 30 mapped"). */
    protected static function mappingProgress($show): string
    {
        if (! $show) {
            return 'Items Sold';
        }

        $total  = $show->orders()->count();
        if ($total === 0) {
            return 'Items Sold';
        }

        $mapped = $show->orders()->whereNotNull('inventory_item_id')->count();

        return $mapped === $total
            ? "Items Sold — all {$total} mapped ✓"
            : "Items Sold — {$mapped} of {$total} mapped";
    }

    /** Items for the dropdown: the streamer's own stock, else all active items. */
    protected static function streamerInventoryOptions($show): array
    {
        $locationIds = $show?->primaryStreamer()?->inventoryLocations()->pluck('id') ?? collect();

        $query = InventoryItem::query()->where('is_active', true);

        if ($locationIds->isNotEmpty()) {
            $scoped = (clone $query)
                ->whereHas('stock', fn ($s) => $s->whereIn('inventory_location_id', $locationIds))
                ->orderBy('name')
                ->pluck('name', 'id')
                ->toArray();
            if (! empty($scoped)) {
                return $scoped;
            }
        }

        return $query->orderBy('name')->pluck('name', 'id')->toArray();
    }

    public function table(Table $table): Table
    {
        $owner  = $this->getOwnerRecord();
        $show   = $owner->show;
        // Once the entry is approved, a streamer can only view its items.
        $locked = \App\Filament\Resources\StreamerLogResource::isLockedForCurrentUser($owner);

        return $table
            ->recordTitleAttribute('item_name')
            ->defaultSort('lot_number')
            ->columns([
                TextColumn::make('lot_number')->label('Lot #')->width('72px')->placeholder('—')->toggleable(),

                TextColumn::make('item_name')->label('Item')->wrap()->placeholder('—'),

                TextColumn::make('buyer_username')->label('Buyer')->placeholder('—')->toggleable(),

                TextInputColumn::make('quantity')
                    ->label('Qty')->type('number')->rules(['required', 'integer', 'min:1'])->width('72px')
                    ->summarize(Sum::make()->label('Units'))
                    ->disabled($locked),

                TextColumn::make('total_price')->label('Sold For')->money('USD')->placeholder('—')
                    ->summarize(Sum::make()->label('Total')->money('USD')),

                SelectColumn::make('inventory_item_id')
                    ->label('Inventory Item')
                    ->options(fn () => self::streamerInventoryOptions($show))
                    ->selectablePlaceholder('— map item —')
                    ->width('220px')
                    ->disabled($locked),

                SelectColumn::make('inventory_location_id')
                    ->label('Location')
                    ->options(function () use ($show) {
                        $streamer = $show?->primaryStreamer();
                        $own = $streamer
                            ? $streamer->inventoryLocations()->orderBy('name')->pluck('name', 'id')->toArray()
                            : [];
                        return ! empty($own)
                            ? $own
                            : InventoryLocation::query()->orderBy('name')->pluck('name', 'id')->toArray();
                    })
                    ->selectablePlaceholder('—')
                    ->width('160px')
                    ->disabled($locked),

                TextInputColumn::make('unit_cost')
                    ->label('Unit Cost')->type('number')->rules(['nullable', 'numeric', 'min:0'])->width('110px')
                    ->disabled($locked),

                TextColumn::make('total_cost')->label('Total Cost')->money('USD')->placeholder('—')->weight('bold')
                    ->summarize(Sum::make()->label('Total')->money('USD')),
            ])
            ->heading(fn () => static::mappingProgress($show))
            ->headerActions([
                // One-click cost entry from each mapped item's inventory cost.
                Action::make('fill_costs')
                    ->label('Fill Costs')
                    ->icon('heroicon-o-sparkles')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalHeading('Fill unit costs from inventory')
                    ->modalDescription('Set the unit cost for every mapped item that has no cost yet, from that inventory item\'s current cost. Items you\'ve already priced are left untouched.')
                    ->visible(fn () => $show !== null && ! $locked)
                    ->action(function () use ($show): void {
                        $orders = $show->orders()
                            ->whereNotNull('inventory_item_id')
                            ->where(fn ($q) => $q->whereNull('unit_cost')->orWhere('unit_cost', 0))
                            ->with('inventoryItem')
                            ->get();

                        $filled = 0;
                        foreach ($orders as $order) {
                            $cost = $order->inventoryItem?->unit_cost;
                            if ($cost !== null && (float) $cost > 0) {
                                $order->unit_cost = $cost;
                                $order->save();
                                $filled++;
                            }
                        }

                        Notification::make()
                            ->title($filled > 0 ? "Filled costs on {$filled} item" . ($filled === 1 ? '' : 's') : 'Nothing to fill')
                            ->success()
                            ->send();
                    }),
            ])
            ->emptyStateHeading('No items yet')
            ->emptyStateDescription('Items sold import automatically once this show\'s orders are pulled from Whatnot.')
            ->emptyStateIcon('heroicon-o-shopping-cart')
            ->paginated([25, 50, 100]);
    }
}
