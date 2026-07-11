<?php

namespace App\Filament\Resources\ShowResource\RelationManagers;

use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryStock;
use App\Models\WhatnotShowOrder;
use App\Services\WhatnotScraper;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
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

    /**
     * Inventory items for the item dropdown: the show streamer's own stock (items
     * with a stock row at any of their locations), falling back to all active
     * items when the streamer has no inventory set up.
     *
     * @return array<int, string>
     */
    protected static function streamerInventoryOptions($show): array
    {
        $locationIds = $show->primaryStreamer()?->inventoryLocations()->pluck('id') ?? collect();

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
        $show = $this->getOwnerRecord();

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
                    // Reference-only column — collapsible so the enrichment table
                    // stays readable on a phone.
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
                    ->formatStateUsing(fn ($state, WhatnotShowOrder $record) =>
                        $state ?: ('@' . ($record->buyer_username ?? '—')))
                    ->description(fn (WhatnotShowOrder $record) =>
                        $record->buyer_display_name ? '@' . $record->buyer_username : null)
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
                    ->color(fn ($state) => match ($state) {
                        'completed' => 'success',
                        'refunded'  => 'danger',
                        'cancelled' => 'danger',
                        'pending'   => 'warning',
                        default     => 'gray',
                    })
                    ->toggleable(),

                // ── Streamer enrichment (inline-editable) ────────────────────────
                // Map the sold item to inventory, its location, and the streamer's
                // cost. total_cost is kept = qty × unit_cost by the model.
                SelectColumn::make('inventory_item_id')
                    ->label('Inventory Item')
                    // Scope to the streamer's own inventory (items stocked at their
                    // location); fall back to all active items when none is set up.
                    ->options(fn () => self::streamerInventoryOptions($show))
                    ->selectablePlaceholder('— map item —')
                    ->width('220px'),

                SelectColumn::make('inventory_location_id')
                    ->label('Location')
                    // Prefer the show's streamer's own locations; fall back to all.
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
                    ->getTitleFromRecordUsing(fn (WhatnotShowOrder $record) =>
                        $record->buyer_display_name
                            ? "{$record->buyer_display_name} (@{$record->buyer_username})"
                            : ('@' . $record->buyer_username)),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(WhatnotShowOrder::statusLabels()),
            ])
            ->headerActions([
                // Add a new inventory item on the fly and stock it at the streamer's
                // location so it immediately appears in the (scoped) item dropdown.
                Action::make('add_inventory_item')
                    ->label('Add Inventory Item')
                    ->icon('heroicon-o-plus')
                    ->color('gray')
                    ->schema([
                        TextInput::make('name')->required()->maxLength(255),
                        TextInput::make('sku')->label('SKU')->maxLength(255),
                        TextInput::make('category')->maxLength(255),
                        TextInput::make('unit_cost')->numeric()->default(0)->prefix('$'),
                        Select::make('inventory_location_id')
                            ->label('Location')
                            ->options(function () use ($show) {
                                $streamer = $show->primaryStreamer();
                                return $streamer
                                    ? $streamer->inventoryLocations()->orderBy('name')->pluck('name', 'id')->toArray()
                                    : InventoryLocation::query()->orderBy('name')->pluck('name', 'id')->toArray();
                            })
                            ->default(fn () => $show->defaultInventoryLocation()?->id)
                            ->required(),
                    ])
                    ->action(function (array $data): void {
                        $item = InventoryItem::create([
                            'name'      => $data['name'],
                            'sku'       => $data['sku'] ?: null,
                            'category'  => $data['category'] ?: null,
                            'unit_cost' => $data['unit_cost'] ?: 0,
                            'is_active' => true,
                        ]);
                        InventoryStock::firstOrCreate([
                            'inventory_item_id'     => $item->id,
                            'inventory_location_id' => $data['inventory_location_id'],
                        ], ['quantity' => 0]);

                        Notification::make()
                            ->title("Added \"{$item->name}\" to inventory")
                            ->body('It\'s now selectable in the item dropdown for this show.')
                            ->success()
                            ->send();
                    }),

                // One-click cost entry: fill unit_cost for every mapped item that
                // has no cost yet, using that inventory item's current cost — so a
                // streamer doesn't retype what inventory already knows.
                Action::make('fill_costs')
                    ->label('Fill Costs')
                    ->icon('heroicon-o-sparkles')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalHeading('Fill unit costs from inventory')
                    ->modalDescription('Set the unit cost for every mapped item that has no cost yet, using that inventory item\'s current cost. Items you\'ve already priced are left untouched.')
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
                                $order->unit_cost = $cost; // model keeps total_cost in sync
                                $order->save();
                                $filled++;
                            }
                        }

                        Notification::make()
                            ->title($filled > 0
                                ? "Filled costs on {$filled} item" . ($filled === 1 ? '' : 's')
                                : 'Nothing to fill')
                            ->body($filled > 0
                                ? 'Costs came from each item\'s inventory record. Adjust any inline as needed.'
                                : 'Map items to inventory (and give those items a cost) first.')
                            ->success()
                            ->send();
                    }),

                Action::make('import_orders')
                    ->label('Import Items Sold')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->visible(fn () => (bool) $show->detail_url)
                    ->requiresConfirmation()
                    ->modalHeading('Import Items Sold from Whatnot')
                    ->modalDescription(
                        'This scrapes the order list for this show from Whatnot. ' .
                        'It logs in, navigates to the show detail page, and reads each lot. ' .
                        'May take up to 60 seconds.'
                    )
                    ->action(function () use ($show): void {
                        try {
                            $result = app(WhatnotScraper::class)->importShowOrders($show);

                            Notification::make()
                                ->title('Import complete')
                                ->body(
                                    "{$result['created']} new item" . ($result['created'] !== 1 ? 's' : '') . ' imported' .
                                    ($result['skipped'] ? ", {$result['skipped']} already on file" : '') . '.'
                                )
                                ->success()
                                ->send();
                        } catch (\RuntimeException $e) {
                            Notification::make()
                                ->title('Import failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->emptyStateHeading('No items imported yet')
            ->emptyStateDescription(
                $show->detail_url
                    ? 'Click "Import Items Sold" to pull this show\'s order list from Whatnot.'
                    : 'Run a show import first to capture the Whatnot show URL, then come back here to pull the item list.'
            )
            ->emptyStateIcon('heroicon-o-shopping-cart')
            ->paginated([25, 50, 100]);
    }
}
