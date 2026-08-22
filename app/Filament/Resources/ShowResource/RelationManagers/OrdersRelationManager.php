<?php

namespace App\Filament\Resources\ShowResource\RelationManagers;

use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\WhatnotShowOrder;
use App\Services\WhatnotScraper;
use App\Support\StatusColor;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;

class OrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'orders';
    protected static ?string $title = 'Items Sold';

    /** @return array<int, string> */
    protected static function streamerInventoryOptions($show): array
    {
        $locationIds = $show->primaryStreamer()?->inventoryLocations()->pluck('id') ?? collect();
        $query = InventoryItem::query()->where('is_active', true);

        if ($locationIds->isNotEmpty()) {
            $scoped = (clone $query)
                ->whereHas('stock', fn ($stock) => $stock->whereIn('inventory_location_id', $locationIds))
                ->orderBy('name')
                ->pluck('name', 'id')
                ->toArray();

            if (! empty($scoped)) return $scoped;
        }

        return $query->orderBy('name')->pluck('name', 'id')->toArray();
    }

    public function table(Table $table): Table
    {
        $show = $this->getOwnerRecord();

        if (! $show) return $table->recordTitleAttribute('item_name');

        return $table
            ->recordTitleAttribute('item_name')
            ->deferLoading()
            ->defaultSort('lot_number')
            ->columns([
                TextColumn::make('lot_number')
                    ->label('Lot #')
                    ->sortable()
                    ->width('72px')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('item_name')
                    ->label('Item')
                    ->searchable()
                    ->wrap()
                    ->placeholder('—'),

                TextColumn::make('buyer_display_name')
                    ->label('Buyer')
                    ->searchable(query: fn ($query, $search) => $query
                        ->where('buyer_display_name', 'like', "%{$search}%")
                        ->orWhere('buyer_username', 'like', "%{$search}%"))
                    ->formatStateUsing(fn ($state, WhatnotShowOrder $record) => $state ?: ('@' . ($record->buyer_username ?? '—')))
                    ->description(fn (WhatnotShowOrder $record) => $record->buyer_display_name ? '@' . $record->buyer_username : null)
                    ->toggleable(),

                TextInputColumn::make('quantity')
                    ->label('Qty')
                    ->type('number')
                    ->rules(['required', 'integer', 'min:1'])
                    ->width('72px'),

                TextColumn::make('unit_price')
                    ->label('Unit')
                    ->money('USD')
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('total_price')
                    ->label('Total')
                    ->money('USD')
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => WhatnotShowOrder::statusLabels()[$state] ?? $state)
                    ->color(fn ($state) => StatusColor::for($state))
                    ->toggleable(),

                SelectColumn::make('inventory_item_id')
                    ->label('Inventory Item')
                    ->options(fn () => self::streamerInventoryOptions($show))
                    ->selectablePlaceholder('— map item —')
                    ->width('220px'),

                SelectColumn::make('inventory_location_id')
                    ->label('Location')
                    ->options(function () use ($show) {
                        $streamer = $show->primaryStreamer();
                        $own = $streamer
                            ? $streamer->inventoryLocations()->orderBy('name')->pluck('name', 'id')->toArray()
                            : [];

                        return ! empty($own)
                            ? $own
                            : InventoryLocation::query()->orderBy('name')->pluck('name', 'id')->toArray();
                    })
                    ->selectablePlaceholder('—')
                    ->width('160px'),

                TextInputColumn::make('unit_cost')
                    ->label('Unit Cost')
                    ->type('number')
                    ->rules(['nullable', 'numeric', 'min:0'])
                    ->width('110px'),

                TextColumn::make('total_cost')
                    ->label('Total Cost')
                    ->money('USD')
                    ->placeholder('—')
                    ->weight('bold'),
            ])
            ->groups([
                Group::make('buyer_username')
                    ->label('Buyer')
                    ->getTitleFromRecordUsing(fn (WhatnotShowOrder $record) => $record->buyer_display_name
                        ? "{$record->buyer_display_name} (@{$record->buyer_username})"
                        : ('@' . $record->buyer_username)),
            ])
            ->filters([
                SelectFilter::make('status')->options(WhatnotShowOrder::statusLabels()),
            ])
            ->headerActions([
                Action::make('fill_costs')
                    ->label('Fill Costs')
                    ->icon('heroicon-o-sparkles')
                    ->color('gray')
                    ->visible(fn () => auth()->user()?->isAdmin() ?? false)
                    ->requiresConfirmation()
                    ->modalHeading('Fill unit costs from inventory')
                    ->modalDescription('Fill missing costs from already-mapped inventory items. Inventory creation is handled from the Inventory area, not from a show.')
                    ->action(function () use ($show): void {
                        $orders = $show->orders()
                            ->whereNotNull('inventory_item_id')
                            ->where(fn ($query) => $query->whereNull('unit_cost')->orWhere('unit_cost', 0))
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
                            ->title($filled > 0 ? "Filled costs on {$filled} item(s)" : 'Nothing to fill')
                            ->success()
                            ->send();
                    }),

                Action::make('import_orders')
                    ->label('Import Items Sold')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->visible(fn () => (bool) $show->detail_url && (auth()->user()?->isAdmin() ?? false))
                    ->requiresConfirmation()
                    ->modalHeading('Import Items Sold from Whatnot')
                    ->modalDescription('Refresh this show’s sold-item reference data from Whatnot.')
                    ->action(function () use ($show): void {
                        try {
                            $result = app(WhatnotScraper::class)->importShowOrders($show);

                            Notification::make()
                                ->title('Import complete')
                                ->body("{$result['created']} new item(s) imported" . ($result['skipped'] ? ", {$result['skipped']} already on file" : '') . '.')
                                ->success()
                                ->send();
                        } catch (\RuntimeException $exception) {
                            Notification::make()
                                ->title('Import failed')
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->emptyStateHeading('No items imported yet')
            ->emptyStateDescription($show->detail_url
                ? 'Import Items Sold to pull Whatnot order reference data for this show.'
                : 'This show does not have a Whatnot detail URL yet.')
            ->emptyStateIcon('heroicon-o-shopping-cart')
            ->paginated([25, 50, 100]);
    }
}
