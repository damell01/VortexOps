<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasModuleAccess;
use App\Filament\Resources\ShipmentResource\Pages;
use App\Models\Shipment;
use App\Support\AdminModules;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

class ShipmentResource extends Resource
{
    use HasModuleAccess;

    protected static ?string $model = Shipment::class;

    // These are Whatnot show shipments, so they belong beside Shows rather than
    // under the purchasing/vendor-shipment module.
    protected static string $moduleSlug = 'streams';

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-truck';
    }

    public static function getNavigationLabel(): string
    {
        return 'Show Shipments';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return AdminModules::navigationGroupFor('streams');
    }

    public static function getNavigationSort(): ?int
    {
        return 2;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = Cache::remember('nav_badge:show_shipments_pending', 60, fn () =>
            static::getEloquentQuery()
                ->whereRaw("LOWER(COALESCE(status, '')) <> 'delivered'")
                ->count()
        );

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function canCreate(): bool { return false; }
    public static function canEdit($record): bool { return false; }
    public static function canDelete($record): bool { return false; }
    public static function canDeleteAny(): bool { return false; }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['show.channel', 'show.streamers'])
            ->whereHas('show', fn (Builder $query) => $query->inChannelContext());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->deferLoading()
            ->persistFiltersInSession()
            ->defaultSort('created_at_whatnot', 'desc')
            ->columns([
                TextColumn::make('show.title')
                    ->label('Show')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->description(fn (Shipment $record) => $record->show?->show_date?->format('M j, Y'))
                    ->url(fn (Shipment $record) => $record->show
                        ? ShowResource::getUrl('view', ['record' => $record->show])
                        : null),

                TextColumn::make('show.streamers.name')
                    ->label('Streamer')
                    ->badge()
                    ->separator(', ')
                    ->placeholder('Unassigned'),

                TextColumn::make('show.channel.name')
                    ->label('Channel')
                    ->badge()
                    ->placeholder('—')
                    ->toggleable(),

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
                        if ($state === null || $state === '') return '—';
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
                        if (! is_array($d) || $d === []) return '—';
                        $l = $d['box_length_in'] ?? $d['length_in'] ?? $d['length'] ?? null;
                        $w = $d['box_width_in'] ?? $d['width_in'] ?? $d['width'] ?? null;
                        $h = $d['box_height_in'] ?? $d['height_in'] ?? $d['height'] ?? null;
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
                        'in_transit', 'in transit', 'shipped' => 'info',
                        'pending', 'pending delivery', 'ready to ship', 'label_created', 'label created' => 'warning',
                        'failed', 'returned', 'cancelled', 'canceled' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('carrier')
                    ->label('Carrier')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),

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
                SelectFilter::make('show_id')
                    ->label('Show')
                    ->relationship('show', 'title')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('carrier')
                    ->label('Carrier')
                    ->options(fn () => Shipment::query()
                        ->whereNotNull('carrier')
                        ->distinct()
                        ->orderBy('carrier')
                        ->pluck('carrier', 'carrier')
                        ->all()),

                SelectFilter::make('status')
                    ->label('Status')
                    ->options(fn () => Shipment::query()
                        ->whereNotNull('status')
                        ->distinct()
                        ->orderBy('status')
                        ->pluck('status', 'status')
                        ->mapWithKeys(fn ($value, $key) => [$key => ucwords(str_replace('_', ' ', $value))])
                        ->all()),

                Filter::make('pending_delivery')
                    ->label('Pending delivery')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->whereRaw('LOWER(COALESCE(status, \'\')) <> ?', ['delivered'])),
            ])
            ->actions([
                Action::make('view_show')
                    ->label('View Show')
                    ->icon('heroicon-o-video-camera')
                    ->url(fn (Shipment $record) => $record->show
                        ? ShowResource::getUrl('view', ['record' => $record->show])
                        : null)
                    ->visible(fn (Shipment $record) => (bool) $record->show),
            ])
            ->paginationPageOptions([25, 50, 100])
            ->defaultPaginationPageOption(50)
            ->striped()
            ->emptyStateIcon('heroicon-o-truck')
            ->emptyStateHeading('No show shipments yet')
            ->emptyStateDescription('Whatnot shipment records will appear here automatically and stay tied to their shows.');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListShipments::route('/'),
        ];
    }
}
