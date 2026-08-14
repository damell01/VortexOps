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
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The fulfillment team's own workspace: sold items from shows they're
 * assigned to, scoped so they never see other streamers' cost/pay data —
 * just what needs packing, shipping, and tracking.
 */
class FulfillmentResource extends Resource
{
    use HasModuleAccess;

    protected static ?string $model = Show::class;
    protected static string $moduleSlug = 'fulfillment';
    protected static ?string $slug = 'fulfillment-center';

    protected static ?string $navigationLabel = 'Fulfillment Center';
    protected static ?string $modelLabel      = 'show';

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-truck';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return AdminModules::navigationGroupFor('fulfillment');
    }

    public static function getNavigationSort(): ?int
    {
        return 44;
    }

    protected static function passesModuleAccessCheck(): bool
    {
        $user = auth()->user();

        return ($user?->isAdmin() || $user?->isOwner() || $user?->isFulfillment() || $user?->isFulfillmentAdmin()) ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with(['streamers', 'channel', 'fulfillmentUsers'])
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->whereHas('orders');

        $user = auth()->user();

        $seesAllRows = $user && ($user->isAdmin() || $user->isOwner() || $user->isFulfillmentAdmin());

        // Regular fulfillment users see only shows they're assigned to.
        if (! $seesAllRows && $user && $user->isFulfillment()) {
            $query->whereHas('fulfillmentUsers', fn (Builder $q) => $q->where('users.id', $user->id));
        }

        // Channel context applies to everyone. Admins used to return early
        // above, which skipped this — so picking a channel in the sidebar
        // switcher did nothing here while every other resource honoured it.
        if (ChannelContext::isScoped()) {
            $query->where('whatnot_channel_id', ChannelContext::currentId());
        }

        return $query;
    }

    // A curated view onto existing shows — no create/edit/delete of the show itself.
    // canView() is overridden too: the Show model's Shield-generated policy gates
    // on a "View:Show" permission nobody holds (permissions are unseeded), which
    // would 403 every non-admin here. This resource has its own access rule.
    public static function canView(\Illuminate\Database\Eloquent\Model $record): bool
    {
        $user = auth()->user();

        return ($user?->isAdmin() || $user?->isOwner() || $user?->isFulfillment() || $user?->isFulfillmentAdmin()) ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Show')->columns(3)->columnSpanFull()->schema([
                Placeholder::make('title')->label('Show')->content(fn (?Show $record) => $record?->title ?: '—'),
                Placeholder::make('show_date')->label('Date')->content(fn (?Show $record) => $record?->show_date?->format('M j, Y') ?? '—'),
                Placeholder::make('status')->label('Status')->content(fn (?Show $record) => $record ? (Show::statusLabels()[$record->status] ?? $record->status) : '—'),
                Placeholder::make('streamer')->label('Streamer')->content(fn (?Show $record) => $record?->streamers->pluck('name')->join(', ') ?: '—'),
                Placeholder::make('channel')->label('Channel')->content(fn (?Show $record) => $record?->channel?->name ?? '—'),
                Placeholder::make('units_sold')->label('Units Sold')->content(fn (?Show $record) => $record?->units_sold ?? '—'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->striped()
            ->persistFiltersInSession()
            ->columns([
                TextColumn::make('show_date')
                    ->label('Date')
                    ->date('M j, Y')
                    ->sortable(),

                TextColumn::make('title')
                    ->label('Show')
                    ->searchable()
                    ->limit(40)
                    ->default('—'),

                TextColumn::make('streamers.name')
                    ->label('Streamer')
                    ->badge()
                    ->separator(', '),

                TextColumn::make('channel.name')
                    ->label('Channel')
                    ->placeholder('—')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('orders_count')
                    ->label('Items')
                    ->counts('orders'),

                TextColumn::make('shipping_progress')
                    ->label('Shipping Progress')
                    ->state(function (Show $record): string {
                        $total = $record->orders()->count();
                        if ($total === 0) return '—';
                        $shipped = $record->orders()->whereIn('shipping_status', ['shipped', 'delivered'])->count();
                        $percent = intval(($shipped / $total) * 100);
                        return "{$shipped}/{$total} ({$percent}%)";
                    })
                    ->badge()
                    ->color(fn (Show $record): string => static::isFullyShipped($record) ? 'success' : 'warning'),
                TextColumn::make('fulfillment_next_action')
                    ->label('Next Action')
                    ->state(function (Show $record): string {
                        $total = $record->orders()->count();
                        if ($total === 0) return 'No items';
                        $shipped = $record->orders()->whereIn('shipping_status', ['shipped', 'delivered'])->count();
                        $ready = $record->orders()->where('shipping_status', 'ready_to_ship')->count();

                        return match (true) {
                            $shipped === $total => 'Complete ✓',
                            $ready > 0 => "Ship {$ready} items",
                            default => 'Prepare items',
                        };
                    })
                    ->badge()
                    ->color(function (Show $record): string {
                        $total = $record->orders()->count();
                        if ($total === 0) return 'gray';
                        $shipped = $record->orders()->whereIn('shipping_status', ['shipped', 'delivered'])->count();

                        return $shipped === $total ? 'success' : 'warning';
                    }),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(Show::statusLabels()),
            ])
            ->defaultSort('show_date', 'desc')
            ->emptyStateIcon('heroicon-o-truck')
            ->emptyStateHeading('No shows to fulfill')
            ->emptyStateDescription('Shows with sold items you\'re assigned to will show up here.');
    }

    private static function shippingProgressLabel(Show $record): string
    {
        $total = $record->orders()->count();
        if ($total === 0) {
            return '—';
        }
        $shipped = $record->orders()->whereIn('shipping_status', ['shipped', 'delivered'])->count();

        return "{$shipped} / {$total} shipped";
    }

    private static function isFullyShipped(Show $record): bool
    {
        $total = $record->orders()->count();
        if ($total === 0) {
            return false;
        }

        return $record->orders()->whereIn('shipping_status', ['shipped', 'delivered'])->count() === $total;
    }

    public static function getRelations(): array
    {
        return [
            FulfillmentOrdersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFulfillmentShows::route('/'),
            'view'  => Pages\ViewFulfillmentShow::route('/{record}'),
        ];
    }
}
