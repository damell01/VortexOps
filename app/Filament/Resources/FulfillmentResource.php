<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasModuleAccess;
use App\Filament\Resources\FulfillmentResource\Pages;
use App\Filament\Resources\FulfillmentResource\RelationManagers\FulfillmentOrdersRelationManager;
use App\Models\Show;
use App\Support\AdminModules;
use App\Support\ChannelContext;
use Filament\Forms\Components\Placeholder;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FulfillmentResource extends Resource
{
    use HasModuleAccess;

    protected static ?string $model = Show::class;
    protected static string $moduleSlug = 'fulfillment';
    protected static ?string $slug = 'fulfillment-center';
    protected static ?string $navigationLabel = 'Fulfillment Center';
    protected static ?string $modelLabel = 'show';

    public static function getNavigationIcon(): string|\BackedEnum|null { return 'heroicon-o-truck'; }
    public static function getNavigationGroup(): string|\UnitEnum|null { return AdminModules::navigationGroupFor('fulfillment'); }
    public static function getNavigationSort(): ?int { return 44; }

    protected static function passesModuleAccessCheck(): bool
    {
        $user = auth()->user();
        return ($user?->isAdmin() || $user?->isOwner() || $user?->isFulfillment() || $user?->isFulfillmentAdmin()) ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with(['streamers', 'channel', 'fulfillmentUsers', 'streamerLogEntry'])
            ->withCount('orders')
            ->withCount([
                'orders as pending_packing_count' => fn ($q) => $q->where(function ($pending) {
                    $pending->whereNull('shipping_status')
                        ->orWhereIn('shipping_status', ['', 'pending', 'label_created']);
                }),
            ])
            ->withCount('shipments')
            ->withCount([
                'shipments as delivered_shipments_count' => fn ($q) => $q->whereRaw("LOWER(COALESCE(status, '')) = 'delivered'"),
                'shipments as open_shipments_count' => fn ($q) => $q->whereRaw("LOWER(COALESCE(status, '')) <> 'delivered'"),
            ])
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->where(function ($q) {
                $q->whereHas('shipments')
                    ->orWhereHas('orders')
                    ->orWhereHas('streamerLogEntry', fn ($log) => $log
                        ->where(function ($approved) {
                            $approved->where('status', 'admin_approved')
                                ->orWhere('approval_status', 'approved');
                        }));
            });

        $user = auth()->user();
        $seesAllRows = $user && ($user->isAdmin() || $user->isOwner() || $user->isFulfillmentAdmin());

        if (! $seesAllRows && $user && $user->isFulfillment()) {
            $query->whereHas('fulfillmentUsers', fn (Builder $q) => $q->where('users.id', $user->id));
        }

        if (ChannelContext::isScoped()) {
            $query->where('whatnot_channel_id', ChannelContext::currentId());
        }

        return $query;
    }

    public static function canView(\Illuminate\Database\Eloquent\Model $record): bool
    {
        if (\App\Support\RoleAccess::grants(static::class)) {
            return true;
        }

        $user = auth()->user();
        return ($user?->isAdmin() || $user?->isOwner() || $user?->isFulfillment() || $user?->isFulfillmentAdmin()) ?? false;
    }

    public static function canCreate(): bool { return false; }
    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool { return false; }
    public static function canDeleteAny(): bool { return false; }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Show')->columns(3)->columnSpanFull()->schema([
                Placeholder::make('title')->label('Show')->content(fn (?Show $record) => $record?->title ?: '—'),
                Placeholder::make('show_date')->label('Date')->content(fn (?Show $record) => $record?->show_date?->format('M j, Y') ?? '—'),
                Placeholder::make('status')->label('Show Status')->content(fn (?Show $record) => $record ? (Show::statusLabels()[$record->status] ?? $record->status) : '—'),
                Placeholder::make('streamer')->label('Streamer')->content(fn (?Show $record) => $record?->streamers->pluck('name')->join(', ') ?: '—'),
                Placeholder::make('fulfillment')->label('Fulfillment')->content(fn (?Show $record) => $record?->fulfillmentUsers->pluck('name')->join(', ') ?: 'Unassigned'),
                Placeholder::make('units_sold')->label('Whatnot Orders')->content(fn (?Show $record) => $record?->units_sold ?? '—'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordUrl(fn (Show $record) => static::getUrl('view', ['record' => $record]))
            ->persistFiltersInSession()
            ->defaultSort('show_date', 'desc')
            ->columns([
                TextColumn::make('show_date')->label('Date')->date('M j')->sortable()->visibleFrom('md'),
                TextColumn::make('title')
                    ->label('Show')
                    ->searchable()
                    ->wrap()
                    ->limit(42)
                    ->icon(fn ($record) => $record->is_slow_pack ? 'heroicon-m-clock' : null)
                    ->iconColor('warning')
                    ->description(fn ($record) => $record->is_slow_pack
                        ? 'Takes a while' . (filled($record->fulfillment_notes) ? ' — ' . \Illuminate\Support\Str::limit($record->fulfillment_notes, 70) : '')
                        : (filled($record->fulfillment_notes) ? \Illuminate\Support\Str::limit($record->fulfillment_notes, 70) : null))
                    ->tooltip(fn ($record) => $record->fulfillment_notes),
                TextColumn::make('streamers.name')->label('Streamer')->badge()->separator(', ')->placeholder('Unassigned')->visibleFrom('md'),
                TextColumn::make('fulfillmentUsers.name')->label('Fulfillment')->badge()->separator(', ')->placeholder('Unassigned')->visibleFrom('lg'),
                TextColumn::make('pending_packing_count')->label('To Pack')->numeric()->badge()->color(fn ($state) => (int) $state > 0 ? 'warning' : 'success')->sortable(),
                TextColumn::make('open_shipments_count')->label('Open Shipments')->numeric()->badge()->color(fn ($state) => (int) $state > 0 ? 'info' : 'success')->sortable(),
                TextColumn::make('delivered_shipments_count')->label('Delivered')->numeric()->sortable()->visibleFrom('lg'),
                TextColumn::make('fulfillment_next_action')
                    ->label('Next')
                    ->state(function (Show $record): string {
                        $approved = $record->streamerLogEntry && (
                            $record->streamerLogEntry->status === 'admin_approved'
                            || $record->streamerLogEntry->approval_status === 'approved'
                        );
                        if ($record->fulfillmentUsers->isEmpty()) return 'Assign';
                        if ($record->streamerLogEntry?->needsFulfillmentReview()) return 'Verify counts';
                        if ((int) $record->pending_packing_count > 0) return 'Pack orders';
                        if ((int) $record->open_shipments_count > 0) return 'Work shipments';
                        if ($approved && (int) $record->shipments_count === 0) return 'Await shipment data';
                        if ((int) $record->shipments_count > 0) return 'Complete ✓';
                        return 'Waiting';
                    })
                    ->badge()
                    ->color(function (Show $record): string {
                        if ($record->fulfillmentUsers->isEmpty()) return 'warning';
                        if ($record->streamerLogEntry?->needsFulfillmentReview()) return 'warning';
                        if ((int) $record->pending_packing_count > 0) return 'primary';
                        if ((int) $record->open_shipments_count > 0) return 'info';
                        if ((int) $record->shipments_count > 0) return 'success';
                        return 'gray';
                    }),
            ])
            ->filters([
                SelectFilter::make('status')->options(Show::statusLabels()),
                SelectFilter::make('fulfillment_user')->label('Assigned To')->relationship('fulfillmentUsers', 'name')->searchable()->preload(),
                \Filament\Tables\Filters\TernaryFilter::make('is_slow_pack')
                    ->label('Takes a while')->placeholder('All shows')->trueLabel('Flagged as slow')->falseLabel('Not flagged'),
            ])
            ->paginationPageOptions([25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->emptyStateIcon('heroicon-o-truck')
            ->emptyStateHeading('No fulfillment work')
            ->emptyStateDescription('Admin-approved shows and shows with packing or shipment work appear here.');
    }

    public static function getRelations(): array
    {
        return [FulfillmentOrdersRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFulfillmentShows::route('/'),
            'view' => Pages\ViewFulfillmentShow::route('/{record}'),
        ];
    }
}
