<?php

namespace App\Filament\Resources\StreamerLogResource\RelationManagers;

use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\WhatnotShowOrder;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid as SchemaGrid;
use Filament\Schemas\Schema;
use Filament\Tables\Actions\Action as TableAction;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * The items sold on the log entry's show, editable straight from the Streamer
 * Log page — so a streamer maps each item to inventory and confirms its cost
 * without opening the individual show. Most rows import automatically from
 * Whatnot; the streamer can also add a row manually (e.g. an off-platform
 * sale) and remove any manually-added row. Imported rows can't be deleted.
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

    /** Generate a random alphanumeric SKU (8 chars, uppercase). */
    protected static function generateSku(): string
    {
        do {
            $sku = strtoupper(Str::random(8));
        } while (InventoryItem::where('sku', $sku)->exists());

        return $sku;
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

    /** Form for manually adding an item the streamer sold that wasn't auto-imported from Whatnot. */
    public function form(Schema $schema): Schema
    {
        $show = $this->getOwnerRecord()->show;

        return $schema->components([
            SchemaGrid::make(2)->schema([
                Select::make('inventory_item_id')
                    ->label('Item')
                    ->options(fn () => self::streamerInventoryOptions($show))
                    ->searchable()
                    ->required()
                    ->columnSpanFull(),

                TextInput::make('quantity')
                    ->numeric()
                    ->minValue(1)
                    ->default(1)
                    ->required(),

                Select::make('inventory_location_id')
                    ->label('Location')
                    ->options(function () use ($show) {
                        $streamer = $show?->primaryStreamer();
                        $own = $streamer
                            ? $streamer->inventoryLocations()->orderBy('name')->pluck('name', 'id')->toArray()
                            : [];
                        return ! empty($own)
                            ? $own
                            : InventoryLocation::query()->orderBy('name')->pluck('name', 'id')->toArray();
                    }),

                TextInput::make('unit_cost')
                    ->label('Unit Cost ($)')
                    ->numeric()
                    ->minValue(0),

                TextInput::make('unit_price')
                    ->label('Sold For ($, optional)')
                    ->numeric()
                    ->minValue(0),
            ]),
        ]);
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
                // On phones the table slims down to what a streamer actually edits
                // (item, qty, mapping, cost); context columns return at md+ so the
                // row fits without sideways scrolling on a narrow screen.
                TextColumn::make('lot_number')->label('Lot #')->width('72px')->placeholder('—')
                    ->visibleFrom('md')->toggleable(),

                TextColumn::make('item_name')->label('Item')->wrap()->placeholder('—'),

                TextColumn::make('buyer_username')->label('Buyer')->placeholder('—')
                    ->visibleFrom('md')->toggleable(),

                TextInputColumn::make('quantity')
                    ->label('Qty')->type('number')->rules(['required', 'integer', 'min:1'])->width('72px')
                    ->summarize(Sum::make()->label('Units'))
                    ->disabled($locked),

                TextColumn::make('total_price')->label('Sold For')->money('USD')->placeholder('—')
                    ->visibleFrom('md')
                    ->summarize(Sum::make()->label('Total')->money('USD')),

                TextColumn::make('inventoryItem.name')
                    ->label('Inventory Item')
                    ->placeholder('— map item —')
                    ->weight('bold')
                    ->color('primary')
                    ->width('200px'),

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
                    ->visibleFrom('md')
                    ->disabled($locked),

                TextInputColumn::make('unit_cost')
                    ->label('Unit Cost')->type('number')->rules(['nullable', 'numeric', 'min:0'])->width('110px')
                    ->disabled($locked),

                TextColumn::make('total_cost')->label('Total Cost')->money('USD')->placeholder('—')->weight('bold')
                    ->visibleFrom('md')
                    ->summarize(Sum::make()->label('Total')->money('USD')),
            ])
            ->heading(fn () => static::mappingProgress($show))
            ->headerActions([
                // Lets a streamer add something they sold that wasn't auto-imported
                // from Whatnot (e.g. an off-platform sale, or the import missed a lot).
                CreateAction::make()
                    ->label('Add Item')
                    ->visible(fn () => ! $locked)
                    ->mutateFormDataUsing(function (array $data) use ($show): array {
                        $item = InventoryItem::find($data['inventory_item_id'] ?? null);
                        $qty  = (int) ($data['quantity'] ?? 1);

                        $data['item_name']   = $item?->name;
                        $data['show_date']   = $show?->show_date;
                        $data['status']      = 'completed';
                        $data['unit_price']  = $data['unit_price'] ?? null;
                        $data['total_price'] = $data['unit_price'] !== null ? round((float) $data['unit_price'] * $qty, 2) : null;

                        return $data;
                    }),

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
            ->recordActions([
                TableAction::make('selectInventoryItem')
                    ->label('Select')
                    ->icon('heroicon-o-cube')
                    ->disabled($locked)
                    ->color('info')
                    ->modalHeading('Map Inventory Item')
                    ->modalDescription('Select an existing item from your inventory or quickly add a new one')
                    ->modalWidth('6xl')
                    ->form([
                        Section::make('Select Existing Item')
                            ->description('Pick from your active inventory')
                            ->schema([
                                Select::make('inventory_item_id')
                                    ->label('Item')
                                    ->options(function () use ($show) {
                                        $items = InventoryItem::query()->where('is_active', true)->orderBy('name')->get();
                                        return $items->mapWithKeys(function ($item) {
                                            $stock = $item->stock->sum('quantity_on_hand') ?? 0;
                                            $cost = $item->unit_cost ? " • \${$item->unit_cost}" : '';
                                            $sku = $item->sku ? " [{$item->sku}]" : '';
                                            $label = "{$item->name}{$sku} (Stock: {$stock}){$cost}";
                                            return [$item->id => $label];
                                        })->toArray();
                                    })
                                    ->searchable()
                                    ->placeholder('Search items...')
                                    ->columnSpanFull(),

                                TextInput::make('barcode_or_upc')
                                    ->label('Scan Barcode/UPC')
                                    ->placeholder('Scan barcode or enter UPC...')
                                    ->helperText('Scan the item barcode directly or paste a UPC')
                                    ->columnSpanFull(),
                            ])
                            ->collapsible(false),

                        Section::make('Or Create New Item')
                            ->description('Add a new inventory item')
                            ->schema([
                                TextInput::make('new_item_name')
                                    ->label('Item Name')
                                    ->placeholder('e.g., Premium Card Lot #42...')
                                    ->columnSpan(2)
                                    ->required(fn (callable $get) => ! empty($get('inventory_item_id')) ? false : true),

                                TextInput::make('new_item_sku')
                                    ->label('SKU')
                                    ->placeholder('Auto-generated')
                                    ->helperText('Leave blank for auto-generation')
                                    ->columnSpan(1),

                                TextInput::make('new_item_barcode')
                                    ->label('Barcode/UPC')
                                    ->placeholder('Optional barcode...')
                                    ->columnSpan(2),

                                TextInput::make('new_item_cost')
                                    ->label('Cost ($)')
                                    ->numeric()
                                    ->minValue(0)
                                    ->step(0.01)
                                    ->placeholder('0.00')
                                    ->columnSpan(1),

                                Select::make('new_item_location')
                                    ->label('Default Location')
                                    ->options(function () use ($show) {
                                        $streamer = $show?->primaryStreamer();
                                        $own = $streamer
                                            ? $streamer->inventoryLocations()->orderBy('name')->pluck('name', 'id')->toArray()
                                            : [];
                                        return ! empty($own)
                                            ? $own
                                            : InventoryLocation::query()->orderBy('name')->pluck('name', 'id')->toArray();
                                    })
                                    ->placeholder('Select a location...')
                                    ->columnSpan(3),
                            ])
                            ->collapsible(false),

                        Section::make('Mapping Details')
                            ->description('Additional information for this mapping')
                            ->schema([
                                TextInput::make('quantity_mapped')
                                    ->label('Quantity for This Item')
                                    ->numeric()
                                    ->minValue(1)
                                    ->default(1)
                                    ->helperText('How many units of this item were sold')
                                    ->columnSpan(1),

                                Select::make('location_id')
                                    ->label('Pick Location')
                                    ->options(function () use ($show) {
                                        $streamer = $show?->primaryStreamer();
                                        $own = $streamer
                                            ? $streamer->inventoryLocations()->orderBy('name')->pluck('name', 'id')->toArray()
                                            : [];
                                        return ! empty($own)
                                            ? $own
                                            : InventoryLocation::query()->orderBy('name')->pluck('name', 'id')->toArray();
                                    })
                                    ->placeholder('Location to pull from...')
                                    ->columnSpan(2),
                            ])
                            ->collapsible(false),
                    ])
                    ->action(function (array $data, WhatnotShowOrder $record): void {
                        // Create new item if name provided
                        if ($data['new_item_name'] ?? null) {
                            $sku = $data['new_item_sku'] ?: self::generateSku();
                            $item = InventoryItem::create([
                                'name' => $data['new_item_name'],
                                'sku' => $sku,
                                'barcode' => $data['new_item_barcode'] ?? null,
                                'unit_cost' => $data['new_item_cost'] ?? null,
                                'is_active' => true,
                            ]);
                            $record->update(['inventory_item_id' => $item->id]);

                            Notification::make()
                                ->title('✓ Item created')
                                ->body("\"{$item->name}\" (SKU: {$sku}) added to inventory and mapped.")
                                ->success()
                                ->send();
                        }
                        // Use existing item
                        elseif ($data['inventory_item_id'] ?? null) {
                            $item = InventoryItem::find($data['inventory_item_id']);
                            $record->update(['inventory_item_id' => $item->id]);

                            if ($item && $item->hasBeenOutOfStockFor(14)) {
                                Notification::make()
                                    ->title('⚠️ Low stock warning')
                                    ->body("\"{$item->name}\" has had zero stock for 14+ days.")
                                    ->warning()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title('✓ Item mapped')
                                    ->success()
                                    ->send();
                            }
                        }
                    }),

                // Only manually-added rows (no whatnot_order_id) can be removed —
                // imported order lines stay as the source of truth for the show.
                DeleteAction::make()
                    ->visible(fn (WhatnotShowOrder $record) => empty($record->whatnot_order_id) && ! $locked),
            ])
            ->emptyStateHeading('No items yet')
            ->emptyStateDescription('Items sold import automatically once this show\'s orders are pulled from Whatnot, or add one yourself with "Add Item".')
            ->emptyStateIcon('heroicon-o-shopping-cart')
            ->paginated([25, 50, 100]);
    }
}
