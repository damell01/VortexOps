<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasModuleAccess;
use App\Filament\Resources\InventoryLocationResource\Pages;
use App\Models\DeductionRequestLine;
use App\Models\InventoryLocation;
use App\Support\AdminModules;
use App\Support\ChannelContext;
use App\Support\StatusColor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Actions\Action as TableAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class InventoryLocationResource extends Resource
{
    /** Secondary inventory tool: reached from Inventory Overview; permissions remain unchanged. */
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    use HasModuleAccess;

    protected static string $moduleSlug  = 'inventory';

    protected static ?string $model = InventoryLocation::class;

    // View-only for streamers — the row scoping below already limited them to
    // their own + shared locations, but the access gate never actually let
    // them in to see it.
    protected static function passesModuleAccessCheck(): bool
    {
        $user = auth()->user();

        return ($user?->isAdmin() || $user?->isOwner() || $user?->isStreamer()) ?? false;
    }

    // No canAccess() override here on purpose. Overriding it bypasses
    // HasModuleAccess::canAccess() — and with it the module toggle, the
    // NavVisibility rules, and passesModuleAccessCheck() above, which is the
    // thing that admits streamers. The override said admin/owner only, so it
    // silently undid the fix directly above it. The trait handles this.

    // No shouldRegisterNavigation() override either, for the same reason as
    // canAccess() above. It hardcoded admin/owner, which undid the streamer
    // admission in passesModuleAccessCheck() one method later — the page was
    // reachable but had no link, so ticking it Visible on Roles & Permissions
    // appeared to do nothing. The trait derives the link from canAccess().

    public static function canCreate(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        // "Can Edit" on Roles & Permissions, which used to be a checkbox
        // nothing read.
        if (\App\Support\RoleAccess::allowsEditing(static::class)) {
            return true;
        }

        return auth()->user()?->isAdmin() ?? false;
    }

    // Streamers see only their own locations + shared locations (no streamer assigned)
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['streamer', 'channel', 'businesses']);
        $user  = auth()->user();

        if ($user && $user->isStreamer() && ! $user->isAdmin()) {
            // Their own, plus shared ones for everyone or for their business.
            $streamerId = $user->streamer?->id;
            $businessId = $user->streamer?->business_id;
            $query->where(function (Builder $q) use ($streamerId, $businessId): void {
                $q->where(fn (Builder $shared) => $shared->whereNull('streamer_id')
                    ->where(fn (Builder $b) => $b->whereDoesntHave('businesses')
                        ->when($businessId, fn ($w) => $w->orWhereHas('businesses', fn ($x) => $x->whereKey($businessId)))))
                  ->orWhere('streamer_id', $streamerId);
            });
        }

        if (ChannelContext::isScoped()) {
            $query->where('whatnot_channel_id', ChannelContext::currentId());
        }

        return $query;
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-map-pin';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return AdminModules::navigationGroupFor('inventory');
    }

    public static function getNavigationSort(): ?int
    {
        return 6;
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name'];
    }

    public static function getGlobalSearchResultTitle(\Illuminate\Database\Eloquent\Model $record): string
    {
        return $record->name;
    }

    public static function getGlobalSearchResultDetails(\Illuminate\Database\Eloquent\Model $record): array
    {
        // Lowercase keys are slots in the global-search override, not labels:
        // subtitle / status / tone / figure. See that view for the contract.
        $type = InventoryLocation::typeLabels()[$record->type] ?? $record->type;

        $subtitle = array_filter([
            // "Main Storage" is both a location name and a type, so the
            // subtitle would otherwise just repeat the title back.
            $type === $record->name ? null : $type,
            $record->streamer?->name,
        ]);

        $skus = (int) ($record->stock_count ?? 0);

        return array_filter([
            'subtitle' => implode(' · ', $subtitle),
            'status'   => InventoryLocation::statusLabels()[$record->status] ?? $record->status,
            'tone'     => $record->status === 'active' ? 'success' : 'neutral',
            'figure'   => $skus > 0 ? number_format($skus) . ' ' . Str::plural('SKU', $skus) : null,
        ]);
    }

    /**
     * getEloquentQuery() already eager-loads the streamer the subtitle reads,
     * but a count has to be selected explicitly — without it the figure is
     * always absent rather than wrong, which is the harder kind to notice.
     */
    public static function getGlobalSearchEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getGlobalSearchEloquentQuery()->withCount('stock');
    }

    /**
     * inventory_stock.inventory_location_id cascade-deletes, so a location still
     * holding stock can't be removed without silently wiping those stock rows.
     * deduction_request_lines.inventory_location_id has no delete rule (defaults
     * to restrict), so a referenced location would otherwise 500 on delete.
     */
    public static function canDelete(Model $record): bool
    {
        if (! auth()->user()?->isAdmin()) {
            return false;
        }

        if ($record->stock()->where('quantity', '>', 0)->exists()) {
            return false;
        }

        return ! DeductionRequestLine::where('inventory_location_id', $record->id)->exists();
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function getNavigationLabel(): string
    {
        return 'Locations';
    }

    /** What each type is for, in the words the setup form uses. */
    public static function typeDescriptions(): array
    {
        return [
            'main_storage' => 'Where stock is kept and received into. Streamers sell from it.',
            'streamer_inventory' => 'Stock a streamer holds and sells from themselves.',
            'fulfillment' => 'Packing and shipping area.',
            'returned' => 'Returns waiting to be checked. Not sold from.',
            'damaged' => 'Damaged stock. Not sold from.',
            'other' => 'Anything else.',
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('What is it?')->afterHeader([\App\Support\HelpTopics::button('locations.type')])->columnSpanFull()->schema([
                TextInput::make('name')
                    ->label('Name')
                    ->placeholder('e.g. Estill Storage, Jay\'s Shelf')
                    ->required()
                    ->maxLength(255),
                \Filament\Forms\Components\Radio::make('type')
                    ->label('What it\'s for')
                    ->options(InventoryLocation::typeLabels())
                    ->descriptions(static::typeDescriptions())
                    ->columns(['default' => 1, 'md' => 3])
                    ->default('main_storage')
                    ->required(),
            ]),
            Section::make('Who uses it?')
                ->afterHeader([\App\Support\HelpTopics::button('locations.audience')])
                ->description('Streamers sell from their own locations plus the storage they share. A business with storage of its own uses only that, not the shared Main Storage.')
                ->columnSpanFull()->schema([
                    \Filament\Forms\Components\Radio::make('audience')
                        ->hiddenLabel()
                        ->options([
                            'everyone' => 'Everyone',
                            'business' => 'Certain businesses',
                            'streamer' => 'One streamer',
                        ])
                        ->descriptions([
                            'everyone' => 'Shared by every business that has no storage of its own.',
                            'business' => 'Only their streamers. Pick one (Estill\'s own allocation) or several to share it.',
                            'streamer' => 'Only this person.',
                        ])
                        ->inline()
                        ->default('everyone')
                        ->live()
                        ->afterStateHydrated(fn ($component, ?InventoryLocation $record) => $record ? $component->state($record->audience()) : null),
                    \Filament\Forms\Components\CheckboxList::make('business_ids')
                        ->label('Shared by')
                        ->options(fn () => \App\Models\Business::ordered()->pluck('name', 'id')->all())
                        ->columns(['default' => 1, 'md' => 3])
                        ->required(fn ($get) => $get('audience') === 'business')
                        ->visible(fn ($get) => $get('audience') === 'business')
                        ->afterStateHydrated(fn ($component, ?InventoryLocation $record) => $record ? $component->state(array_map('strval', $record->sharedBusinessIds())) : null),
                    Select::make('streamer_id')
                        ->label('Streamer')
                        ->options(fn (?\App\Models\InventoryLocation $record) => static::streamerOptions($record?->streamer_id))
                        ->required(fn ($get) => $get('audience') === 'streamer')
                        ->visible(fn ($get) => $get('audience') === 'streamer'),
                ]),
            Section::make('More options')->collapsed()->columnSpanFull()->schema([
                Grid::make(2)->schema([
                    Select::make('status')
                        ->options(InventoryLocation::statusLabels())
                        ->required()
                        ->default('active'),
                    Select::make('whatnot_channel_id')
                        ->label('Channel')
                        ->relationship('channel', 'name')
                        ->helperText('Which channel this location\'s stock is grouped under.'),
                ]),
                Textarea::make('notes')
                    ->rows(3)
                    ->columnSpanFull(),
            ]),
        ]);
    }

    /** "Jay · Estill" for the streamer picker, so two people's business is clear. */
    /** @param int|null $keep a saved owner to list even if they've since been archived */
    public static function streamerOptions(?int $keep = null): array
    {
        $businesses = \App\Models\Business::pluck('short_name', 'id')->map(fn ($s, $id) => $s ?: \App\Models\Business::whereKey($id)->value('name'));

        return \App\Models\Streamer::query()->streamers()
            ->where(fn ($q) => $q->where('status', 'active')->when($keep, fn ($q) => $q->orWhere('id', $keep)))
            ->orderBy('name')
            ->get(['id', 'name', 'business_id'])
            ->mapWithKeys(fn ($s) => [$s->id => $s->name . ($s->business_id && isset($businesses[$s->business_id]) ? ' · ' . $businesses[$s->business_id] : '')])
            ->all();
    }

    /**
     * Turn the form's "Who uses it?" into the columns: a streamer's location
     * also records their business, so it lists under that business.
     */
    public static function applyAudience(array $data): array
    {
        $audience = $data['audience'] ?? 'everyone';
        $businessIds = $audience === 'business' ? array_values(array_map('intval', (array) ($data['business_ids'] ?? []))) : [];
        unset($data['audience'], $data['business_ids']);

        if ($audience === 'streamer' && ! empty($data['streamer_id'])) {
            $data['business_id'] = \App\Models\Streamer::whereKey($data['streamer_id'])->value('business_id');
        } else {
            $data['streamer_id'] = null;
            $data['business_id'] = null;
        }

        return ['data' => $data, 'business_ids' => $businessIds];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->emptyStateHeading('No locations yet')
            ->emptyStateDescription('Create a location to start tracking where stock lives.')
            ->emptyStateIcon('heroicon-o-map-pin')
            ->deferLoading()
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold')
                    // Whose location it is belongs with the name, not in a
                    // column that is empty for every non-streamer location.
                    ->description(fn (InventoryLocation $record) => $record->notes ? \Illuminate\Support\Str::limit($record->notes, 60) : null)
                    ->extraCellAttributes(['class' => 'vx-col-title'])
                    ->extraHeaderAttributes(['class' => 'vx-col-title']),
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => InventoryLocation::typeLabels()[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'main_storage' => 'info',
                        'streamer_inventory' => 'success',
                        'returned' => 'warning',
                        'damaged' => 'danger',
                        'fulfillment' => 'primary',
                        default => 'gray',
                    }),
                TextColumn::make('used_by')
                    ->label('Used by')
                    ->state(fn (InventoryLocation $record) => $record->audienceLabel())
                    ->badge()
                    ->icon(fn (InventoryLocation $record) => match ($record->audience()) {
                        'streamer' => 'heroicon-m-user',
                        'business' => 'heroicon-m-building-storefront',
                        default => 'heroicon-m-user-group',
                    })
                    ->color(fn (InventoryLocation $record) => match ($record->audience()) {
                        'streamer' => 'success',
                        'business' => 'primary',
                        default => 'gray',
                    }),
                TextColumn::make('stock_count')
                    // "Item Count", not "SKUs": the phone card omits any column
                    // whose label looks like a code — "SKUs" matched that rule
                    // and the number vanished from the card entirely.
                    ->label('Item Count')
                    ->counts('stock')
                    ->extraCellAttributes(['class' => 'vx-col-tight'])
                    ->extraHeaderAttributes(['class' => 'vx-col-tight']),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => InventoryLocation::statusLabels()[$state] ?? $state)
                    ->color(fn ($state) => StatusColor::for($state))
                    ->extraCellAttributes(['class' => 'vx-col-tight'])
                    ->extraHeaderAttributes(['class' => 'vx-col-tight']),
                TextColumn::make('streamer.name')
                    ->label('Streamer')
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('channel.name')
                    ->label('Channel')
                    ->badge()
                    ->color('gray')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options(InventoryLocation::typeLabels()),
                SelectFilter::make('status')
                    ->options(InventoryLocation::statusLabels()),
                SelectFilter::make('business')
                    ->label('Business')
                    ->options(fn () => \App\Models\Business::ordered()->pluck('name', 'id')->all())
                    // Shared with it, or a streamer's own location in it.
                    ->query(fn (Builder $query, array $data) => filled($data['value'] ?? null)
                        ? $query->where(fn (Builder $q) => $q->whereHas('businesses', fn ($b) => $b->whereKey($data['value']))->orWhere('business_id', $data['value']))
                        : $query),
                SelectFilter::make('streamer_id')
                    ->label('Streamer')
                    ->relationship('streamer', 'name'),
                SelectFilter::make('whatnot_channel_id')
                    ->label('Channel')
                    ->relationship('channel', 'name'),
            ])
            ->headerActions([
                TableAction::make('export_csv')
                    ->label('Download CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->url(fn () => route('export.locations'))
                    ->openUrlInNewTab(),
            ])
            ->actions([
                ViewAction::make()
                    ->size('sm')
                    ->iconButton(),
                EditAction::make()
                    ->size('sm')
                    ->iconButton(),
                DeleteAction::make()
                    ->iconButton()
                    ->visible(fn (InventoryLocation $record) => static::canDelete($record))
                    ->tooltip(fn (InventoryLocation $record) => $record->stock()->where('quantity', '>', 0)->exists()
                        ? 'Still holds stock — move or zero it out first'
                        : null),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->action(function (Collection $records): void {
                            $deletable = $records->filter(fn (InventoryLocation $record) => static::canDelete($record));
                            $blocked   = $records->count() - $deletable->count();

                            $deletable->each->delete();

                            if ($blocked > 0) {
                                Notification::make()
                                    ->title($deletable->count() . ' location(s) deleted')
                                    ->body("{$blocked} skipped — still hold stock or are referenced by a deduction request.")
                                    ->warning()
                                    ->send();
                            } else {
                                Notification::make()->title($deletable->count() . ' location(s) deleted')->success()->send();
                            }
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->striped()
            ->persistFiltersInSession()
            ->paginationPageOptions(\App\Support\MobileTablePagination::options([10, 25, 50]))
            ->defaultPaginationPageOption(\App\Support\MobileTablePagination::defaultPageSize(25))
            ->defaultSort('name');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInventoryLocations::route('/'),
            'create' => Pages\CreateInventoryLocation::route('/create'),
            'view' => Pages\ViewInventoryLocation::route('/{record}'),
            'edit' => Pages\EditInventoryLocation::route('/{record}/edit'),
        ];
    }
}
