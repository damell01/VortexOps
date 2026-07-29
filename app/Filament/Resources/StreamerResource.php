<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasModuleAccess;
use App\Filament\Concerns\HasAdminNavVisibility;
use App\Filament\Resources\StreamerResource\Pages;
use App\Filament\Resources\StreamerResource\RelationManagers\LoansRelationManager;
use App\Models\DeductionRequest;
use App\Models\ShippingSurcharge;
use App\Models\Streamer;
use App\Support\AdminModules;
use App\Support\ChannelContext;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class StreamerResource extends Resource
{
    use HasModuleAccess, HasAdminNavVisibility;

    protected static string $moduleSlug  = 'operations';

    protected static ?string $model = Streamer::class;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-user-group';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return AdminModules::navigationGroupFor('operations');
    }

    public static function getNavigationSort(): ?int
    {
        return 1;
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'legal_name', 'email'];
    }

    public static function getGlobalSearchResultTitle(\Illuminate\Database\Eloquent\Model $record): string
    {
        return $record->name;
    }

    public static function getGlobalSearchResultDetails(\Illuminate\Database\Eloquent\Model $record): array
    {
        return array_filter([
            'Email'       => $record->email,
            'Payout Type' => Streamer::payoutTypeLabels()[$record->payout_type] ?? $record->payout_type,
        ]);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getEloquentQuery()->withCount('inventoryLocations');

        if (ChannelContext::isScoped()) {
            $query->where('whatnot_channel_id', ChannelContext::currentId());
        }

        return $query;
    }

    // Streamers cannot manage other streamers
    public static function canCreate(): bool    { return auth()->user()?->isAdmin() ?? false; }
    public static function canEdit($r): bool    { return auth()->user()?->isAdmin() ?? false; }
    public static function canDeleteAny(): bool { return auth()->user()?->isAdmin() ?? false; }

    /**
     * Payouts and deduction_requests have no delete rule on streamer_id
     * (defaults to restrict) — deleting a streamer with either would 500.
     * show_streamer, streamer_loans, shipping_surcharges, and
     * streamer_log_entries all cascade on delete — allowing it there would
     * silently wipe show attribution, loan, surcharge, and log history
     * instead of erroring. Block delete whenever any of that exists.
     */
    public static function canDelete(Model $record): bool
    {
        if (! auth()->user()?->isAdmin()) {
            return false;
        }

        return ! $record->shows()->exists()
            && ! $record->payouts()->exists()
            && ! $record->loans()->exists()
            && ! $record->streamerLogEntries()->exists()
            && ! DeductionRequest::where('streamer_id', $record->id)->exists()
            && ! ShippingSurcharge::where('streamer_id', $record->id)->exists();
    }

    /** Formula term each builder checkbox contributes, in the order they're joined. */
    private static function formulaComponents(): array
    {
        return [
            'component_package'      => 'package_rate',
            'component_profit_share' => '(payout_percentage / 100) * streamer_share_net',
            'component_hourly'       => 'hourly_rate * show_duration_hours',
            'component_tips'         => 'tip_share',
            'component_pwe'          => 'pwe_rate',
            'component_labels'       => 'label_rate',
        ];
    }

    /** Pre-check a builder checkbox on edit if its term already appears in the saved formula. */
    private static function formulaHasTerm(?Streamer $record, string $term): bool
    {
        return $record && str_contains((string) $record->custom_payout_formula, $term);
    }

    private static function rebuildFormula(Get $get, Set $set): void
    {
        $terms = [];

        foreach (static::formulaComponents() as $checkbox => $term) {
            if ($get($checkbox)) {
                $terms[] = $term;
            }
        }

        $set('custom_payout_formula', implode(' + ', $terms));
    }

    /**
     * Pre-checks a builder checkbox from the saved formula on edit. Deliberately
     * afterStateHydrated(), not default(): default() is silently skipped once a
     * field's state path already exists in the hydrated array, which it always
     * does here since none of these checkboxes are real model attributes —
     * Filament's blanket "fill unmapped fields with null" pass runs first and
     * already puts a null/false entry in the array under this checkbox's key.
     */
    private static function precheckFromFormula(string $term): \Closure
    {
        return function (Checkbox $component, ?Streamer $record) use ($term) {
            $component->state(static::formulaHasTerm($record, $term));
        };
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Scorecard')
                ->description('Performance across every show this streamer was on.')
                ->icon('heroicon-o-trophy')
                ->visible(fn ($record) => $record?->exists && $record->scorecard()['has_data'])
                ->columnSpanFull()
                ->schema([
                    Grid::make(4)->schema([
                        Placeholder::make('sc_shows')
                            ->label('Shows')
                            ->content(fn ($record) => number_format($record->scorecard()['shows'])),
                        Placeholder::make('sc_gross')
                            ->label('Gross Driven')
                            ->content(fn ($record) => '$' . number_format($record->scorecard()['gross'], 2)),
                        Placeholder::make('sc_margin')
                            ->label('Margin Contributed')
                            ->content(fn ($record) => '$' . number_format($record->scorecard()['margin'], 2)),
                        Placeholder::make('sc_rating')
                            ->label('Avg Rating')
                            ->content(function ($record) {
                                $c = $record->scorecard();
                                return $c['avg_rating'] !== null
                                    ? number_format($c['avg_rating'], 2) . " ⭐ ({$c['rated_shows']} rated)"
                                    : '—';
                            }),
                    ]),
                ]),

            Section::make('Basic Information')->columnSpanFull()->schema([
                Grid::make(3)->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('legal_name')
                        ->maxLength(255),
                    TextInput::make('email')
                        ->email()
                        ->maxLength(255),
                    TextInput::make('phone')
                        ->tel()
                        ->maxLength(50),
                    Select::make('whatnot_channel_id')
                        ->label('Channel')
                        ->relationship('channel', 'name')
                        ->searchable()
                        ->preload()
                        ->helperText('Primary channel this streamer is attributed to for stats and analytics.'),
                ]),
            ]),

            // Not model attributes — CreateStreamer pulls these out before the
            // Streamer row is saved and creates the linked User in afterCreate.
            Section::make('Login Account')
                ->description('Optionally create a login so this streamer can sign in and see their own shows, payouts, and inventory.')
                ->columnSpanFull()
                ->visible(fn (string $operation): bool => $operation === 'create')
                ->schema([
                    Grid::make(3)->schema([
                        Toggle::make('create_login')
                            ->label('Create a login for this streamer')
                            ->live()
                            ->inline(false),
                        TextInput::make('login_email')
                            ->label('Login Email')
                            ->email()
                            ->maxLength(255)
                            ->unique(table: 'users', column: 'email')
                            ->visible(fn (Get $get): bool => (bool) $get('create_login'))
                            ->required(fn (Get $get): bool => (bool) $get('create_login'))
                            ->helperText('The email the streamer will sign in with.'),
                        TextInput::make('login_password')
                            ->label('Password')
                            ->password()
                            ->revealable()
                            ->minLength(8)
                            ->maxLength(255)
                            ->visible(fn (Get $get): bool => (bool) $get('create_login'))
                            ->required(fn (Get $get): bool => (bool) $get('create_login')),
                    ]),
                ]),

            Section::make('Payout Configuration')->columnSpanFull()->schema([
                Grid::make(3)->schema([
                    Select::make('payout_type')
                        ->options(Streamer::payoutTypeLabels())
                        ->required()
                        ->live(),
                    Select::make('payout_cadence')
                        ->label('Pay Run Cadence')
                        ->options(Streamer::payoutCadenceLabels())
                        ->default('weekly')
                        ->required()
                        ->helperText('Regular payout types run weekly; profit share and tips are usually batched monthly instead.'),
                    // PWE + Labels fields (native type)
                    TextInput::make('pwe_rate')
                        ->label('PWE Rate ($ per package)')
                        ->helperText('PWE = Plain White Envelope — the pay per single-card envelope shipped.')
                        ->numeric()
                        ->prefix('$')
                        ->minValue(0)
                        ->visible(fn (Get $get) => $get('payout_type') === 'pwe_labels'
                            || ($get('payout_type') === 'custom_formula' && $get('component_pwe'))),
                    TextInput::make('label_rate')
                        ->label('Label Rate ($ per label)')
                        ->numeric()
                        ->prefix('$')
                        ->minValue(0)
                        ->visible(fn (Get $get) => $get('payout_type') === 'pwe_labels'
                            || ($get('payout_type') === 'custom_formula' && $get('component_labels'))),

                    // Hybrid fields (also uses hourly_rate and payout_percentage)
                    TextInput::make('hourly_rate')
                        ->numeric()
                        ->prefix('$')
                        ->suffix('/hr')
                        ->visible(fn (Get $get) => in_array($get('payout_type'), ['hourly', 'hybrid', 'pwe_labels'])
                            || ($get('payout_type') === 'custom_formula' && $get('component_hourly'))),
                    TextInput::make('payout_percentage')
                        ->numeric()
                        ->suffix('%')
                        ->label(fn (Get $get) => $get('payout_type') === 'hybrid' ? 'Profit Share %' : 'Payout %')
                        ->visible(fn (Get $get) => in_array($get('payout_type'), ['profit_share', 'hybrid'])
                            || ($get('payout_type') === 'custom_formula' && $get('component_profit_share'))),
                    TextInput::make('package_rate')
                        ->numeric()
                        ->prefix('$')
                        ->required(fn (Get $get) => in_array($get('payout_type'), ['package', 'flat_rate']))
                        ->visible(fn (Get $get) => in_array($get('payout_type'), ['package', 'flat_rate'])
                            || ($get('payout_type') === 'custom_formula' && $get('component_package'))),
                    Toggle::make('include_tips')
                        ->default(true)
                        ->helperText(fn (Get $get) => $get('payout_type') === 'custom_formula'
                            ? 'Has no effect on Custom Formula — check "Tips" in the formula builder below instead.'
                            : null),
                ]),

                // ── Formula builder: tick the components that make up this streamer's
                // pay (e.g. Package + Profit Share, or PWE + Labels + Profit Share)
                // and the formula below is assembled for you. Still a plain editable
                // textarea underneath — nothing stops hand-editing it directly.
                Section::make('Formula Builder')
                    ->description('Tick the pieces that make up this streamer\'s pay — the formula below fills in automatically. You can still edit it by hand after.')
                    ->visible(fn (Get $get) => $get('payout_type') === 'custom_formula')
                    ->schema([
                        Grid::make(3)->schema([
                            Checkbox::make('component_package')
                                ->label('Package rate')
                                ->dehydrated(false)
                                ->live()
                                ->afterStateHydrated(static::precheckFromFormula('package_rate'))
                                ->afterStateUpdated(fn (Get $get, Set $set) => static::rebuildFormula($get, $set)),
                            Checkbox::make('component_profit_share')
                                ->label('Profit share %')
                                ->dehydrated(false)
                                ->live()
                                ->afterStateHydrated(static::precheckFromFormula('payout_percentage'))
                                ->afterStateUpdated(fn (Get $get, Set $set) => static::rebuildFormula($get, $set)),
                            Checkbox::make('component_hourly')
                                ->label('Hourly rate')
                                ->dehydrated(false)
                                ->live()
                                ->afterStateHydrated(static::precheckFromFormula('hourly_rate'))
                                ->afterStateUpdated(fn (Get $get, Set $set) => static::rebuildFormula($get, $set)),
                            Checkbox::make('component_tips')
                                ->label('Tips')
                                ->dehydrated(false)
                                ->live()
                                ->afterStateHydrated(static::precheckFromFormula('tip_share'))
                                ->afterStateUpdated(fn (Get $get, Set $set) => static::rebuildFormula($get, $set)),
                            Checkbox::make('component_pwe')
                                ->label('PWE rate')
                                ->helperText('Flat, once — not multiplied by envelope count.')
                                ->dehydrated(false)
                                ->live()
                                ->afterStateHydrated(static::precheckFromFormula('pwe_rate'))
                                ->afterStateUpdated(fn (Get $get, Set $set) => static::rebuildFormula($get, $set)),
                            Checkbox::make('component_labels')
                                ->label('Label rate')
                                ->helperText('Flat, once — not multiplied by label count.')
                                ->dehydrated(false)
                                ->live()
                                ->afterStateHydrated(static::precheckFromFormula('label_rate'))
                                ->afterStateUpdated(fn (Get $get, Set $set) => static::rebuildFormula($get, $set)),
                        ]),

                        Textarea::make('custom_payout_formula')
                            ->label('Formula')
                            ->rows(3)
                            ->placeholder('streamer_share_net * 0.35 + tip_share')
                            ->helperText('Auto-filled from the checkboxes above, or write your own. Supported variables: gross_revenue, whatnot_net, streamer_share_net, units_sold, show_duration_hours, show_duration_minutes, tips, tip_share, payout_percentage, package_rate, hourly_rate, pwe_rate, label_rate. Operators: + - * / and parentheses.')
                            ->columnSpanFull(),
                    ]),
            ]),

            Section::make('Burden Rate')
                ->description('Applied to base pay before tips/profit share in Hybrid model. Optional on all models.')
                ->collapsed()
                ->columnSpanFull()
                ->schema([
                    Grid::make(2)->schema([
                        Select::make('burden_rate_type')
                            ->label('Burden Rate Type')
                            ->options(['percentage' => 'Percentage (%)', 'flat' => 'Flat Amount ($)'])
                            ->placeholder('No burden rate')
                            ->nullable()
                            ->live(),
                        TextInput::make('burden_rate_value')
                            ->label(fn ($get) => $get('burden_rate_type') === 'flat' ? 'Burden Amount ($)' : 'Burden Percentage (%)')
                            ->numeric()
                            ->minValue(0)
                            ->visible(fn ($get) => ! empty($get('burden_rate_type'))),
                    ]),
                ]),

            Section::make('Vortex Fee')
                ->description('Leave blank to use the global default set in Settings → Vortex Fee. Set a type here to override it for just this streamer.')
                ->columnSpanFull()->schema([
                Grid::make(3)->schema([
                    Select::make('owner_fee_type')
                        ->label('Fee Type')
                        ->options(Streamer::ownerFeeTypeLabels())
                        ->placeholder('Use global default')
                        ->nullable()
                        ->live(),
                    TextInput::make('owner_fee_value')
                        ->label(fn ($get) => $get('owner_fee_type') === 'flat' ? 'Fee Amount ($)' : 'Fee Percentage (%)')
                        ->numeric()
                        ->minValue(0)
                        ->nullable()
                        ->visible(fn ($get) => ! empty($get('owner_fee_type'))),
                    Toggle::make('owner_fee_deduct_from_payout')
                        ->label('Deduct from payout')
                        ->helperText('On: reduces calculated payout. Off: tracked separately.')
                        ->visible(fn ($get) => ! empty($get('owner_fee_type'))),
                ]),
            ]),

            Section::make('Channel Routing')
                ->description('Map each channel to a specific bank account for payout splits. The routing_bank_label on each payout is set from this table.')
                ->collapsed()
                ->columnSpanFull()
                ->schema([
                    Repeater::make('channel_routing_rules')
                        ->label('')
                        ->schema([
                            TextInput::make('channel')
                                ->label('Channel Name')
                                ->placeholder('e.g. Breaks')
                                ->required()
                                ->maxLength(100),
                            TextInput::make('bank_label')
                                ->label('Bank / Account Label')
                                ->placeholder('e.g. Chase Business x1234')
                                ->required()
                                ->maxLength(255),
                        ])
                        ->columns(2)
                        ->reorderable()
                        ->cloneable()
                        ->addActionLabel('Add routing rule')
                        ->itemLabel(fn (array $state): ?string =>
                            ($state['channel'] ?? null)
                                ? ($state['channel'] . ' → ' . ($state['bank_label'] ?? '?'))
                                : null
                        )
                        ->collapsible()
                        ->columnSpanFull()
                        ->defaultItems(0),
                ]),

            Section::make('Status & Notes')->columnSpanFull()->schema([
                Grid::make(2)->schema([
                    Select::make('status')
                        ->options(Streamer::statusLabels())
                        ->required()
                        ->default('active'),
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
            ->deferLoading()
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('payout_type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => Streamer::payoutTypeLabels()[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'profit_share'   => 'success',
                        'package'        => 'info',
                        'hourly'         => 'warning',
                        'flat_rate'      => 'gray',
                        'pwe_labels'     => 'info',
                        'hybrid'         => 'primary',
                        'custom_formula' => 'primary',
                        default          => 'gray',
                    }),
                TextColumn::make('payout_cadence')
                    ->label('Cadence')
                    ->badge()
                    ->formatStateUsing(fn ($state) => Streamer::payoutCadenceLabels()[$state] ?? $state)
                    ->color(fn ($state) => $state === 'monthly' ? 'primary' : 'gray')
                    ->toggleable(),
                TextColumn::make('total_earnings_due')
                    ->label('Due')
                    ->money('USD')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('total_earnings_paid')
                    ->label('Paid')
                    ->money('USD')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => Streamer::statusLabels()[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'active' => 'success',
                        'inactive' => 'danger',
                        'on_leave' => 'warning',
                        default => 'gray',
                    }),
                IconColumn::make('include_tips')
                    ->boolean()
                    ->label('Tips'),
                TextColumn::make('channel.name')
                    ->label('Channel')
                    ->badge()
                    ->color('gray')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('inventoryLocations_count')
                    ->counts('inventoryLocations')
                    ->label('Locations'),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->emptyStateIcon('heroicon-o-user-group')
            ->emptyStateHeading('No streamers yet')
            ->emptyStateDescription('Add the people you run breaks with — their payout terms, channel routing, and inventory location.')
            ->emptyStateActions([
                \Filament\Actions\CreateAction::make()->label('Add your first streamer'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(Streamer::statusLabels()),
                SelectFilter::make('payout_type')
                    ->options(Streamer::payoutTypeLabels()),
                SelectFilter::make('whatnot_channel_id')
                    ->label('Channel')
                    ->relationship('channel', 'name'),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make()
                    ->iconButton()
                    ->visible(fn (Streamer $record) => static::canDelete($record))
                    ->tooltip(fn (Streamer $record) => static::canDelete($record)
                        ? null
                        : 'Has shows, payouts, loans, surcharges, or log entries — can\'t be deleted while those exist.'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    ExportBulkAction::make(),
                    DeleteBulkAction::make()
                        ->action(function (Collection $records): void {
                            $deletable = $records->filter(fn (Streamer $record) => static::canDelete($record));
                            $blocked   = $records->count() - $deletable->count();

                            $deletable->each->delete();

                            if ($blocked > 0) {
                                Notification::make()
                                    ->title($deletable->count() . ' streamer(s) deleted')
                                    ->body("{$blocked} skipped — still have shows, payouts, loans, surcharges, or log entries.")
                                    ->warning()
                                    ->send();
                            } else {
                                Notification::make()->title($deletable->count() . ' streamer(s) deleted')->success()->send();
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
        return [
            LoansRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStreamers::route('/'),
            'create' => Pages\CreateStreamer::route('/create'),
            'view' => Pages\ViewStreamer::route('/{record}'),
            'edit' => Pages\EditStreamer::route('/{record}/edit'),
        ];
    }
}
