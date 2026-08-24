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
            ->with(['streamers', 'channel', 'fulfillmentUsers'])
            ->withCount('orders')
            ->withCount('shipments')
            ->withCount([
                'shipments as delivered_shipments_count' => fn ($q) => $q->whereRaw("LOWER(COALESCE(status, '')) = 'delivered'"),
                'shipments as open_shipments_count' => fn ($q) => $q->whereRaw("LOWER(COALESCE(status, '')) <> 'delivered'"),
            ])
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->where(function ($q) {
                $q->whereHas('shipments')->orWhereHas('orders');
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
        // A page granted on Roles & Permissions carries its records with it.
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
                Placeholder::make('status')->label('Status')->content(fn (?Show $record) => $record ? (Show::statusLabels()[$record->status] ?? $record->status) : '—'),
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
                // Mobile stays intentionally narrow: show + open work + next action.
                // The context columns return at md+ rather than forcing a sideways
                // table on the phones fulfillment staff actually use.
                TextColumn::make('show_date')
                    ->label('Date')
                    ->date('M j')
                    ->sortable()
                    ->visibleFrom('md'),

                TextColumn::make('title')
                    ->label('Show')
                    ->searchable()
                    ->wrap()
                    ->limit(42),

                TextColumn::make('streamers.name')
                    ->label('Streamer')
                    ->badge()
                    ->separator(', ')
                    ->placeholder('Unassigned')
                    ->visibleFrom('md'),

                TextColumn::make('fulfillmentUsers.name')
                    ->label('Fulfillment')
                    ->badge()
                    ->separator(', ')
                    ->placeholder('Unassigned')
                    ->visibleFrom('lg'),

                TextColumn::make('shipments_count')
                    ->label('Shipments')
                    ->numeric()
                    ->sortable()
                    ->visibleFrom('md'),

                TextColumn::make('open_shipments_count')
                    ->label('Open')
                    ->numeric()
                    ->badge()
                    ->color(fn ($state) => (int) $state > 0 ? 'warning' : 'success')
                    ->sortable(),

                TextColumn::make('delivered_shipments_count')
                    ->label('Delivered')
                    ->numeric()
                    ->sortable()
                    ->visibleFrom('lg'),

                TextColumn::make('orders_count')
                    ->label('Packing Lines')
                    ->numeric()
                    ->visibleFrom('xl')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('fulfillment_next_action')
                    ->label('Next')
                    ->state(function (Show $record): string {
                        if ((int) $record->shipments_count === 0 && (int) $record->orders_count > 0) return 'Prepare';
                        if ((int) $record->open_shipments_count > 0) return 'Work shipments';
                        if ((int) $record->shipments_count > 0) return 'Complete ✓';
                        return 'Waiting';
                    })
                    ->badge()
                    ->color(fn (Show $record) => (int) $record->open_shipments_count > 0 ? 'warning' : ((int) $record->shipments_count > 0 ? 'success' : 'gray')),
            ])
            ->filters([
                SelectFilter::make('status')->options(Show::statusLabels()),
                SelectFilter::make('fulfillment_user')
                    ->label('Assigned To')
                    ->relationship('fulfillmentUsers', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->paginationPageOptions([25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->emptyStateIcon('heroicon-o-truck')
            ->emptyStateHeading('No fulfillment shows')
            ->emptyStateDescription('Assigned shows with imported shipments or packing lines will appear here.');
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
