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
        return auth()->user()?->isAdmin() ?? false;
    }

    // Streamers see only their own locations + shared locations (no streamer assigned)
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['streamer', 'channel']);
        $user  = auth()->user();

        if ($user && $user->isStreamer() && ! $user->isAdmin()) {
            $streamerId = $user->streamer?->id;
            $query->where(function (Builder $q) use ($streamerId): void {
                $q->whereNull('streamer_id')
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

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Location Details')->columnSpanFull()->schema([
                Grid::make(2)->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255),
                    Select::make('type')
                        ->options(InventoryLocation::typeLabels())
                        ->required()
                        ->live(),
                    Select::make('streamer_id')
                        ->label('Assigned Streamer')
                        ->relationship('streamer', 'name')
                        ->searchable()
                        ->preload()
                        ->nullable()
                        ->visible(fn ($get) => $get('type') === 'streamer_inventory'),
                    Select::make('status')
                        ->options(InventoryLocation::statusLabels())
                        ->required()
                        ->default('active'),
                    Select::make('whatnot_channel_id')
                        ->label('Channel')
                        ->relationship('channel', 'name')
                        ->searchable()
                        ->preload()
                        ->helperText('Which channel this location\'s stock is grouped under.'),
                ]),
                Textarea::make('notes')
                    ->rows(3)
                    ->columnSpanFull(),
            ]),
        ]);
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
                    ->description(fn (InventoryLocation $record) => $record->streamer?->name)
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
                SelectFilter::make('streamer_id')
                    ->label('Streamer')
                    ->relationship('streamer', 'name'),
                SelectFilter::make('whatnot_channel_id')
                    ->label('Channel')
                    ->relationship('channel', 'name'),
            ])
            ->headerActions([
                TableAction::make('export_csv')
                    ->label('Export CSV')
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
            ->paginationPageOptions([10, 25, 50])
            ->defaultPaginationPageOption(25)
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
