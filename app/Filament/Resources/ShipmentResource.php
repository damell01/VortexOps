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
            ->heading($show ? 'Shipments · ' . ($show->title ?: 'Show #' . $show->id) : 'Shipments')
            ->description($show ? 'Locked to show #' . $show->id . '. Only shipment records attached to this show are displayed.' : 'Filter shipments by show, status, or carrier.')
            ->deferLoading()
            ->persistFiltersInSession(false)
            ->defaultSort('created_at_whatnot', 'desc')
            ->columns([
                TextColumn::make('buyer_username')
                    ->label('Recipient')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold')
                    ->limit(22)
                    ->tooltip(fn (Shipment $record) => $record->buyer_username)
                    ->description(fn (Shipment $record) => $record->created_at_whatnot?->format('M j, Y') ?? 'Order date unavailable')
                    ->placeholder('—'),
                TextColumn::make('item_count')
                    ->label('Items')
                    ->numeric()
                    ->sortable()
                    ->alignCenter(),
                TextColumn::make('status')
                    ->label('Shipping Status')
                    ->badge()
                    ->formatStateUsing(function ($state): string {
                        $status = strtolower(trim(str_replace(['_', '-'], ' ', (string) $state)));
                        return match ($status) {
                            'label created', 'label printed', 'pre transit', 'pre shipment' => 'Label Created',
                            'ready to ship', 'pending' => 'Ready to Ship',
                            'shipped' => 'Shipped',
                            'in transit' => 'In Transit',
                            'out for delivery' => 'Out for Delivery',
                            'delivered' => 'Delivered',
                            'delivery attempted' => 'Delivery Attempted',
                            'returned', 'return to sender' => 'Returned',
                            'failed', 'exception' => 'Delivery Exception',
                            'cancelled', 'canceled' => 'Cancelled',
                            '', 'unknown' => 'Status Unknown',
                            default => ucwords($status),
                        };
                    })
                    ->color(function ($state): string {
                        $status = strtolower(trim(str_replace(['_', '-'], ' ', (string) $state)));
                        return match ($status) {
                            'delivered' => 'success',
                            'in transit', 'out for delivery', 'shipped' => 'info',
                            'label created', 'label printed', 'pre transit', 'pre shipment', 'pending', 'ready to ship', 'delivery attempted' => 'warning',
                            'failed', 'exception', 'returned', 'return to sender', 'cancelled', 'canceled' => 'danger',
                            default => 'gray',
                        };
                    })
                    ->tooltip(fn (Shipment $record) => filled($record->status)
                        ? 'Latest recorded shipment status: ' . $record->status . '. Not necessarily live carrier tracking.'
                        : 'No shipment status has been recorded yet.')
                    ->sortable(),
                TextColumn::make('carrier')
                    ->label('Carrier / Service')
                    ->searchable()
                    ->formatStateUsing(fn ($state) => $state ? strtoupper((string) $state) : '—')
                    ->description(fn (Shipment $record) => data_get($record->raw_payload, 'shipping_service'))
                    ->placeholder('—'),
                TextColumn::make('tracking_number')
                    ->label('Tracking')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Tracking number copied')
                    ->fontFamily('mono')
                    ->limit(20)
                    ->tooltip(fn (Shipment $record) => $record->tracking_number)
                    ->placeholder('—'),
                TextColumn::make('weight_oz')
                    ->label('Weight / Box')
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
                    ->description(function (Shipment $record): ?string {
                        $d = $record->dimensions_json ?? [];
                        if (! is_array($d) || $d === []) return null;
                        $l = $d['box_length_in'] ?? $d['length_in'] ?? $d['length'] ?? null;
                        $w = $d['box_width_in'] ?? $d['width_in'] ?? $d['width'] ?? null;
                        $h = $d['box_height_in'] ?? $d['height_in'] ?? $d['height'] ?? null;
                        return ($l !== null || $w !== null || $h !== null)
                            ? implode(' × ', array_map(fn ($v) => $v ?? '—', [$l, $w, $h])) . ' in'
                            : null;
                    }),
                TextColumn::make('created_at_whatnot')
                    ->label('Order Date')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('shipment_value')
                    ->label('Shipment Value')
                    ->state(fn (Shipment $record) => data_get($record->raw_payload, 'shipment_value') ?? data_get($record->raw_payload, 'total_price'))
                    ->money('USD')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('shipping_cost')
                    ->label('Shipping Cost')
                    ->money('USD')
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
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
                    ->options(fn () => Show::query()
                        ->orderByRaw('title IS NULL, title')
                        ->limit(100)
                        ->get(['id', 'title'])
                        ->mapWithKeys(fn (Show $show) => [$show->id => filled($show->title) ? $show->title : 'Untitled show #' . $show->id])
                        ->all())
                    ->searchable()
                    ->visible(fn () => ! static::selectedShowId()),
                SelectFilter::make('status')
                    ->options(fn () => Shipment::query()->whereNotNull('status')->where('status', '!=', '')->distinct()->orderBy('status')->pluck('status', 'status')->filter(fn ($label) => filled($label))->all()),
                SelectFilter::make('carrier')
                    ->options(fn () => Shipment::query()->whereNotNull('carrier')->where('carrier', '!=', '')->distinct()->orderBy('carrier')->pluck('carrier', 'carrier')->filter(fn ($label) => filled($label))->all()),
            ])
            ->actions([
                Action::make('view_show')
                    ->label('Open Show')->icon('heroicon-o-video-camera')
                    ->url(fn (Shipment $record) => $record->show ? ShowResource::getUrl('view', ['record' => $record->show]) : null)
                    ->visible(fn (Shipment $record) => (bool) $record->show),
            ])
            ->paginationPageOptions(\App\Support\MobileTablePagination::options([25, 50, 100]))
            ->defaultPaginationPageOption(\App\Support\MobileTablePagination::defaultPageSize(25))
            ->striped()
            ->emptyStateIcon('heroicon-o-truck')
            ->emptyStateHeading('No shipments for this show yet');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListShipments::route('/')];
    }
}