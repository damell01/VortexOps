<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasModuleAccess;
use App\Filament\Resources\FulfillmentResource\Pages;
use App\Models\Show;
use App\Models\StreamerLogItem;
use App\Support\AdminModules;
use App\Support\ChannelContext;
use Filament\Forms\Components\Placeholder;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
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
            ->with(['streamers', 'channel', 'fulfillmentUsers', 'streamerLogEntry.items'])
            ->withCount('shipments')
            ->withCount([
                'shipments as delivered_shipments_count' => fn ($q) => $q->whereRaw("LOWER(COALESCE(status, '')) = 'delivered'"),
                'shipments as open_shipments_count' => fn ($q) => $q->whereRaw("LOWER(COALESCE(status, '')) <> 'delivered'"),
            ])
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->where(function ($q) {
                $q->whereHas('shipments')
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
        if (\App\Support\RoleAccess::grants(static::class)) return true;
        $user = auth()->user();
        return ($user?->isAdmin() || $user?->isOwner() || $user?->isFulfillment() || $user?->isFulfillmentAdmin()) ?? false;
    }

    public static function canCreate(): bool { return false; }
    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool { return false; }
    public static function canDeleteAny(): bool { return false; }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Show')->columns(2)->columnSpanFull()->schema([
                Placeholder::make('title')->label('Show')->content(fn (?Show $record) => $record?->title ?: '—'),
                Placeholder::make('show_date')->label('Date')->content(fn (?Show $record) => $record?->show_date?->format('M j, Y') ?? '—'),
                Placeholder::make('streamer')->label('Streamer')->content(fn (?Show $record) => $record?->streamers->pluck('name')->join(', ') ?: '—'),
                Placeholder::make('fulfillment')->label('Assigned Fulfillment')->content(fn (?Show $record) => $record?->fulfillmentUsers->pluck('name')->join(', ') ?: 'Unassigned'),
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
                TextColumn::make('title')
                    ->label('Show')
                    ->searchable()
                    ->wrap()
                    ->limit(48)
                    ->description(function (Show $record): string {
                        $streamer = $record->streamers->pluck('name')->join(', ') ?: 'No streamer';
                        $assigned = $record->fulfillmentUsers->pluck('name')->join(', ') ?: 'Unassigned';
                        return ($record->show_date?->format('M j') ?? 'No date') . " · {$streamer} · Fulfillment: {$assigned}";
                    }),
                TextColumn::make('fulfillment_next_action')
                    ->label('Next Step')
                    ->state(function (Show $record): string {
                        if ($record->fulfillmentUsers->isEmpty()) return 'Assign fulfillment';
                        $items = $record->streamerLogEntry?->items ?? collect();
                        $pending = $items->filter(fn (StreamerLogItem $item) => ! $item->isFulfillmentReviewed())->count();
                        $issues = $items->where('fulfillment_status', StreamerLogItem::FULFILLMENT_NOT_FULFILLED)->count();
                        if ($issues > 0) return "Resolve {$issues} issue" . ($issues === 1 ? '' : 's');
                        if ($pending > 0) return "Review {$pending} item" . ($pending === 1 ? '' : 's');
                        if ($record->streamerLogEntry?->needsFulfillmentReview()) return 'Verify counts';
                        return 'Complete ✓';
                    })
                    ->badge()
                    ->color(function (Show $record): string {
                        if ($record->fulfillmentUsers->isEmpty()) return 'warning';
                        $items = $record->streamerLogEntry?->items ?? collect();
                        if ($items->where('fulfillment_status', StreamerLogItem::FULFILLMENT_NOT_FULFILLED)->isNotEmpty()) return 'danger';
                        if ($items->filter(fn (StreamerLogItem $item) => ! $item->isFulfillmentReviewed())->isNotEmpty()) return 'primary';
                        if ($record->streamerLogEntry?->needsFulfillmentReview()) return 'warning';
                        return 'success';
                    }),
                TextColumn::make('fulfillment_summary')
                    ->label('Work')
                    ->state(function (Show $record): string {
                        $items = $record->streamerLogEntry?->items ?? collect();
                        $pending = $items->filter(fn (StreamerLogItem $item) => ! $item->isFulfillmentReviewed())->count();
                        $issues = $items->where('fulfillment_status', StreamerLogItem::FULFILLMENT_NOT_FULFILLED)->count();
                        return "{$pending} review · {$issues} issues · " . (int) $record->open_shipments_count . ' open';
                    })
                    ->wrap()
                    ->visibleFrom('md'),
            ])
            ->filters([
                SelectFilter::make('work_stage')
                    ->label('Work Queue')
                    ->options([
                        'unassigned' => 'Needs Assignment',
                        'review' => 'Review Logged Items',
                        'issues' => 'Item Issues',
                        'verify' => 'Verify Counts',
                        'complete' => 'Fulfillment Complete',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'unassigned' => $query->whereDoesntHave('fulfillmentUsers'),
                            'review' => $query->whereHas('streamerLogEntry.items', fn ($items) => $items->whereNull('fulfillment_status')->orWhere('fulfillment_status', StreamerLogItem::FULFILLMENT_PENDING)),
                            'issues' => $query->whereHas('streamerLogEntry.items', fn ($items) => $items->where('fulfillment_status', StreamerLogItem::FULFILLMENT_NOT_FULFILLED)),
                            'verify' => $query->whereHas('streamerLogEntry', fn ($log) => $log->where(function ($approved) {$approved->where('status', 'admin_approved')->orWhere('approval_status', 'approved');})->whereNull('fulfillment_reviewed_at')->whereHas('streamer', fn ($streamer) => $streamer->where('payout_type', 'pwe_labels'))),
                            'complete' => $query->whereHas('streamerLogEntry', fn ($log) => $log->where(function ($approved) {$approved->where('status', 'admin_approved')->orWhere('approval_status', 'approved');}))->whereDoesntHave('streamerLogEntry.items', fn ($items) => $items->whereNull('fulfillment_status')->orWhereIn('fulfillment_status', [StreamerLogItem::FULFILLMENT_PENDING, StreamerLogItem::FULFILLMENT_NOT_FULFILLED]))->whereDoesntHave('streamerLogEntry', fn ($log) => $log->whereNull('fulfillment_reviewed_at')->whereHas('streamer', fn ($streamer) => $streamer->where('payout_type', 'pwe_labels'))),
                            default => $query,
                        };
                    }),
                SelectFilter::make('fulfillment_user')->label('Assigned To')->relationship('fulfillmentUsers', 'name')->searchable()->preload(),
                Filter::make('unassigned_only')->label('Unassigned only')->query(fn (Builder $query) => $query->whereDoesntHave('fulfillmentUsers')),
            ])
            ->paginationPageOptions([15, 25, 50])
            ->defaultPaginationPageOption(15)
            ->emptyStateIcon('heroicon-o-truck')
            ->emptyStateHeading('No fulfillment work')
            ->emptyStateDescription('Shows that are ready for fulfillment appear here automatically.');
    }

    public static function getRelations(): array { return []; }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFulfillmentShows::route('/'),
            'view' => Pages\ViewFulfillmentShow::route('/{record}'),
        ];
    }
}
