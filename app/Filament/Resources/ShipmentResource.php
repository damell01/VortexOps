<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasModuleAccess;
use App\Filament\Resources\ShipmentResource\Pages;
use App\Models\Shipment;
use App\Models\Show;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ShipmentResource extends Resource
{
    use HasModuleAccess;

    protected static ?string $model = Shipment::class;
    protected static string $moduleSlug = 'streams';

    public static function shouldRegisterNavigation(): bool { return false; }
    public static function canCreate(): bool { return false; }
    public static function canEdit($record): bool { return false; }
    public static function canDelete($record): bool { return false; }
    public static function canDeleteAny(): bool { return false; }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    /**
     * A show opened from the Shows workspace is a hard data boundary, not just
     * a cosmetic Filament table filter. This prevents a stale/persisted table
     * filter from ever showing another show's shipment rows under the selected
     * show's URL.
     */
    public static function selectedShowId(): ?int
    {
        $show = request()->query('show');

        if (! $show) {
            $show = data_get(request()->query(), 'tableFilters.show_id.value');
        }

        if (! $show) {
            $show = data_get(request()->query(), 'tableFilters.show_id.values.0');
        }

        return is_numeric($show) && (int) $show > 0 ? (int) $show : null;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with(['show.channel', 'show.streamers'])
            ->whereHas('show', fn (Builder $query) => $query->inChannelContext());

        if ($showId = static::selectedShowId()) {
            $query->where('shipments.show_id', $showId);
        }

        $user = auth()->user();
        if ($user && $user->isStreamer() && ! $user->isAdmin()) {
            $streamerId = $user->streamer?->id ?? 0;
            $query->whereHas('show.streamers', fn (Builder $q) => $q->where('streamers.id', $streamerId));
        }

        return $query;
    }

    public static function table(Table $table): Table
    {
        $showId = static::selectedShowId();
        $show = $showId ? Show::query()->select(['id', 'title'])->find($showId) : null;

        return $table
            ->heading($show ? 'Shipments · ' . $show->title : 'Shipments')
            ->description($show ? 'Locked to show #' . $show->id . '. Only shipment records attached to this show are displayed.' : 'Filter shipments by show, status, or carrier.')
            ->deferLoading()
            ->persistFiltersInSession(false)
            ->defaultSort('created_at_whatnot', 'desc')
            ->columns([
                TextColumn::make('buyer_username')
                    ->label('Recipient')->searchable()->sortable()->placeholder('—'),
                TextColumn::make('created_at_whatnot')
                    ->label('Order Date')->dateTime('M j, Y')->sortable()->placeholder('—'),
                TextColumn::make('item_count')
                    ->label('Items')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('shipment_value')
                    ->label('Shipment Value')
                    ->state(fn (Shipment $record) => data_get($record->raw_payload, 'shipment_value') ?? data_get($record->raw_payload, 'total_price'))
                    ->money('USD')
                    ->placeholder('—'),
                TextColumn::make('shipping_cost')
                    ->label('Shipping Cost')
                    ->money('USD')
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
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
                    }),
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
                    }),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? ucwords(str_replace('_', ' ', $state)) : 'Unknown')
                    ->color(fn ($state) => match (strtolower((string) $state)) {
                        'delivered' => 'success',
                        'in_transit', 'in transit', 'shipped' => 'info',
                        'pending', 'pending delivery', 'ready_to_ship', 'ready to ship', 'label_created', 'label created' => 'warning',
                        'failed', 'returned', 'cancelled', 'canceled' => 'danger',
                        default => 'gray',
                    })->sortable(),
                TextColumn::make('carrier')->label('Carrier')->searchable()->placeholder('—'),
                TextColumn::make('shipping_service')
                    ->label('Service')
                    ->state(fn (Shipment $record) => data_get($record->raw_payload, 'shipping_service'))
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('tracking_number')
                    ->label('Tracking')->searchable()->copyable()->copyMessage('Tracking number copied')->fontFamily('mono')->placeholder('—'),
                IconColumn::make('insurance_added')->label('Insured')->boolean()->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('signature_required')->label('Signature')->boolean()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('show_id')
                    ->label('Show')
                    ->relationship('show', 'title')
                    ->searchable()
                    ->preload()
                    ->visible(fn () => ! static::selectedShowId()),
                SelectFilter::make('status')
                    ->options(fn () => Shipment::query()->whereNotNull('status')->distinct()->orderBy('status')->pluck('status', 'status')->all()),
                SelectFilter::make('carrier')
                    ->options(fn () => Shipment::query()->whereNotNull('carrier')->distinct()->orderBy('carrier')->pluck('carrier', 'carrier')->all()),
            ])
            ->actions([
                Action::make('view_show')
                    ->label('Open Show')->icon('heroicon-o-video-camera')
                    ->url(fn (Shipment $record) => $record->show ? ShowResource::getUrl('view', ['record' => $record->show]) : null)
                    ->visible(fn (Shipment $record) => (bool) $record->show),
            ])
            ->paginationPageOptions([25, 50, 100])
            ->defaultPaginationPageOption(50)
            ->striped()
            ->emptyStateIcon('heroicon-o-truck')
            ->emptyStateHeading('No shipments for this show yet');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListShipments::route('/')];
    }
}
