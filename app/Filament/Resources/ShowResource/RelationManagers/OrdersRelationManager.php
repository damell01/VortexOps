<?php

namespace App\Filament\Resources\ShowResource\RelationManagers;

use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\WhatnotShowOrder;
use App\Services\WhatnotScraper;
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
                    ->placeholder('—'),

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
                        $record->buyer_display_name ? '@' . $record->buyer_username : null),

                TextColumn::make('quantity')
                    ->label('Qty')
                    ->alignCenter()
                    ->width('56px'),

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
                    ->options(fn () => InventoryItem::query()
                        ->where('is_active', true)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->toArray())
                    ->selectablePlaceholder('— map item —')
                    ->width('220px'),

                SelectColumn::make('inventory_location_id')
                    ->label('Location')
                    ->options(fn () => InventoryLocation::query()
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->toArray())
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
