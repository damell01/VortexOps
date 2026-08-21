<?php

namespace App\Filament\Resources\ShowResource\RelationManagers;

use App\Models\Shipment;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ShipmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'shipments';

    protected static ?string $title = 'Shipments';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('tracking_number')
            ->deferLoading()
            ->defaultSort('created_at_whatnot', 'desc')
            ->columns([
                TextColumn::make('buyer_username')
                    ->label('Recipient')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('created_at_whatnot')
                    ->label('Order Date')
                    ->dateTime('M j, Y g:i A')
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('item_count')
                    ->label('Items')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('shipping_cost')
                    ->label('Shipping')
                    ->money('USD')
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('weight_oz')
                    ->label('Weight')
                    ->formatStateUsing(function ($state): string {
                        if ($state === null || $state === '') {
                            return '—';
                        }

                        $oz = (float) $state;
                        if ($oz >= 16) {
                            $lb = floor($oz / 16);
                            $remain = round(fmod($oz, 16), 1);
                            return $remain > 0 ? "{$lb} lb {$remain} oz" : "{$lb} lb";
                        }

                        return rtrim(rtrim(number_format($oz, 1), '0'), '.') . ' oz';
                    })
                    ->toggleable(),

                TextColumn::make('dimensions_json')
                    ->label('Dimensions')
                    ->state(function (Shipment $record): string {
                        $d = $record->dimensions_json ?? [];
                        if (! is_array($d) || $d === []) {
                            return '—';
                        }

                        $l = $d['length_in'] ?? $d['length'] ?? null;
                        $w = $d['width_in'] ?? $d['width'] ?? null;
                        $h = $d['height_in'] ?? $d['height'] ?? null;

                        return ($l !== null || $w !== null || $h !== null)
                            ? implode(' × ', array_map(fn ($v) => $v ?? '—', [$l, $w, $h])) . ' in'
                            : '—';
                    })
                    ->toggleable(),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? ucwords(str_replace('_', ' ', $state)) : 'Unknown')
                    ->color(fn ($state) => match (strtolower((string) $state)) {
                        'delivered' => 'success',
                        'in_transit', 'shipped' => 'info',
                        'pending', 'label_created' => 'warning',
                        'failed', 'returned', 'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('carrier')
                    ->label('Carrier')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('tracking_number')
                    ->label('Tracking')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Tracking number copied')
                    ->fontFamily('mono')
                    ->placeholder('—'),

                IconColumn::make('insurance_added')
                    ->label('Insured')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('signature_required')
                    ->label('Signature')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(fn () => Shipment::query()
                        ->whereNotNull('status')
                        ->distinct()
                        ->orderBy('status')
                        ->pluck('status', 'status')
                        ->mapWithKeys(fn ($value, $key) => [$key => ucwords(str_replace('_', ' ', $value))])
                        ->all()),

                SelectFilter::make('carrier')
                    ->options(fn () => Shipment::query()
                        ->whereNotNull('carrier')
                        ->distinct()
                        ->orderBy('carrier')
                        ->pluck('carrier', 'carrier')
                        ->all()),
            ])
            ->paginationPageOptions([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->striped()
            ->emptyStateIcon('heroicon-o-truck')
            ->emptyStateHeading('No shipments for this show')
            ->emptyStateDescription('Whatnot shipment records will appear here automatically after the show sync runs.');
    }
}
