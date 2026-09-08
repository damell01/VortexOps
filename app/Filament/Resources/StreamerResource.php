<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasModuleAccess;
use App\Filament\Resources\StreamerResource\Pages;
use App\Filament\Resources\StreamerResource\RelationManagers\LoansRelationManager;
use App\Models\DeductionRequest;
use App\Models\ShippingSurcharge;
use App\Models\Streamer;
use App\Support\AdminModules;
use App\Support\ChannelContext;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
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
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class StreamerResource extends Resource
{
    use HasModuleAccess;

    protected static string $moduleSlug = 'operations';
    protected static ?string $model = Streamer::class;
    protected static ?string $slug = 'streamers';

    public static function getNavigationLabel(): string { return 'Team'; }
    public static function getModelLabel(): string { return 'team member'; }
    public static function getPluralModelLabel(): string { return 'Team'; }
    public static function getNavigationIcon(): string|\BackedEnum|null { return 'heroicon-o-user-group'; }
    public static function getNavigationGroup(): string|\UnitEnum|null { return AdminModules::navigationGroupFor('operations'); }
    public static function getNavigationSort(): ?int { return 1; }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'legal_name', 'email'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return $record->name;
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return array_filter([
            'subtitle' => $record->email,
            'status' => Streamer::statusLabels()[$record->status] ?? $record->status,
            'tone' => match ($record->status) {
                'active' => 'success',
                'on_leave' => 'warning',
                'inactive' => 'danger',
                default => 'neutral',
            },
            'figure' => 'Weekly payroll',
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

    public static function canCreate(): bool { return auth()->user()?->isAdmin() ?? false; }
    public static function canEdit($r): bool { return auth()->user()?->isAdmin() ?? false; }
    public static function canDeleteAny(): bool { return auth()->user()?->isAdmin() ?? false; }

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

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Scorecard')
                ->description('Performance across every show this team member was on.')
                ->icon('heroicon-o-trophy')
                ->visible(fn ($record) => $record?->exists && $record->scorecard()['has_data'])
                ->columnSpanFull()
                ->schema([
                    Grid::make(4)->schema([
                        Placeholder::make('sc_shows')->label('Shows')->content(fn ($record) => number_format($record->scorecard()['shows'])),
                        Placeholder::make('sc_gross')->label('Gross Driven')->content(fn ($record) => '$' . number_format($record->scorecard()['gross'], 2)),
                        Placeholder::make('sc_margin')->label('Margin Contributed')->content(fn ($record) => '$' . number_format($record->scorecard()['margin'], 2)),
                        Placeholder::make('sc_rating')->label('Avg Rating')->content(function ($record) {
                            $c = $record->scorecard();
                            return $c['avg_rating'] !== null
                                ? number_format($c['avg_rating'], 2) . " ⭐ ({$c['rated_shows']} rated)"
                                : '—';
                        }),
                    ]),
                ]),

            Section::make('Basic Information')
                ->description('Name, contact info, and assigned channel.')
                ->columnSpanFull()
                ->schema([
                    Grid::make(3)->schema([
                        TextInput::make('name')->required()->maxLength(255),
                        TextInput::make('legal_name')->maxLength(255),
                        TextInput::make('email')->email()->maxLength(255),
                        TextInput::make('phone')->tel()->maxLength(50),
                        Select::make('whatnot_channel_id')
                            ->label('Channel')
                            ->relationship('channel', 'name')
                            ->searchable()
                            ->preload()
                            ->helperText('Primary channel this team member is attributed to for stats and analytics.'),
                    ]),
                ]),

            Section::make('Login Account')
                ->description('Optionally create a login so this person can sign in and see their own shows, payouts, and inventory.')
                ->columnSpanFull()
                ->visible(fn (string $operation): bool => $operation === 'create')
                ->schema([
                    Grid::make(3)->schema([
                        Toggle::make('create_login')
                            ->label('Create a login for this team member')
                            ->live()
                            ->inline(false),
                        TextInput::make('login_email')
                            ->label('Login Email')
                            ->email()
                            ->maxLength(255)
                            ->unique(table: 'users', column: 'email')
                            ->visible(fn (Get $get): bool => (bool) $get('create_login'))
                            ->required(fn (Get $get): bool => (bool) $get('create_login'))
                            ->helperText('The email they will sign in with.'),
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

            Section::make('Payroll')
                ->description('All team members are paid through the weekly Pay Run. Any one-off pay adjustment is handled as an override in the Pay Run, not on the team profile.')
                ->icon('heroicon-o-banknotes')
                ->columnSpanFull()
                ->schema([
                    Placeholder::make('weekly_payroll')
                        ->label('Pay Run Cadence')
                        ->content('Weekly')
                        ->helperText('Compensation calculations and overrides are reviewed in Payroll before the weekly Pay Run is finalized.'),
                ]),

            Section::make('Channel Routing')
                ->description('Map each channel to a specific bank account for payout splits. The routing_bank_label on each payout is set from this table.')
                ->collapsed()
                ->columnSpanFull()
                ->schema([
                    Repeater::make('channel_routing_rules')
                        ->label('')
                        ->schema([
                            TextInput::make('channel')->label('Channel Name')->placeholder('e.g. Breaks')->required()->maxLength(100),
                            TextInput::make('bank_label')->label('Bank / Account Label')->placeholder('e.g. Chase Business x1234')->required()->maxLength(255),
                        ])
                        ->columns(2)
                        ->reorderable()
                        ->cloneable()
                        ->addActionLabel('Add routing rule')
                        ->itemLabel(fn (array $state): ?string => ($state['channel'] ?? null)
                            ? ($state['channel'] . ' → ' . ($state['bank_label'] ?? '?'))
                            : null)
                        ->collapsible()
                        ->columnSpanFull()
                        ->defaultItems(0),
                ]),

            Section::make('Inventory Access Control')
                ->description('Control which inventory locations this team member can access when mapping items. Leave empty to allow access to all locations.')
                ->collapsed()
                ->columnSpanFull()
                ->schema([
                    Repeater::make('inventoryLocations')
                        ->label('Allowed Inventory Locations')
                        ->relationship('inventoryLocations')
                        ->schema([
                            Select::make('name')
                                ->label('Location')
                                ->options(fn () => \App\Models\InventoryLocation::where('status', 'active')
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->toArray())
                                ->searchable()
                                ->required(),
                        ])
                        ->columns(1)
                        ->defaultItems(0)
                        ->addActionLabel('Add Location')
                        ->reorderable()
                        ->collapsible()
                        ->helperText('Streamers will see items from these locations first when mapping sold items.')
                        ->columnSpanFull(),
                ]),

            Section::make('Status & Notes')->columnSpanFull()->schema([
                Grid::make(2)->schema([
                    Select::make('member_type')
                        ->label('Role')
                        ->helperText('This controls where the team member appears in the operations workflow.')
                        ->options(Streamer::memberTypeLabels())
                        ->required()
                        ->default('streamer')
                        ->native(false),
                    Select::make('status')
                        ->options(Streamer::statusLabels())
                        ->required()
                        ->default('active'),
                ]),
                Textarea::make('notes')->rows(3)->columnSpanFull(),
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
                    ->sortable()
                    ->weight('semibold')
                    ->description(fn (Streamer $record) => $record->email)
                    ->extraCellAttributes(['class' => 'vx-col-title'])
                    ->extraHeaderAttributes(['class' => 'vx-col-title']),
                TextColumn::make('member_type')
                    ->label('Role')
                    ->badge()
                    ->formatStateUsing(fn ($state) => Streamer::memberTypeLabels()[$state] ?? 'Streamer')
                    ->color(fn ($state) => $state === 'fulfillment' ? 'info' : 'gray')
                    ->toggleable()
                    ->extraCellAttributes(['class' => 'vx-col-tight'])
                    ->extraHeaderAttributes(['class' => 'vx-col-tight']),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => Streamer::statusLabels()[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'active' => 'success',
                        'inactive' => 'danger',
                        'on_leave' => 'warning',
                        default => 'gray',
                    })
                    ->extraCellAttributes(['class' => 'vx-col-tight'])
                    ->extraHeaderAttributes(['class' => 'vx-col-tight']),
                TextColumn::make('inventoryLocations_count')
                    ->counts('inventoryLocations')
                    ->label('Location Count')
                    ->extraCellAttributes(['class' => 'vx-col-tight'])
                    ->extraHeaderAttributes(['class' => 'vx-col-tight']),
                TextColumn::make('channel.name')
                    ->label('Channel')
                    ->badge()
                    ->color('gray')
                    ->placeholder('—'),
                TextColumn::make('email')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('total_earnings_due')->label('Due')->money('USD')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('total_earnings_paid')->label('Paid')->money('USD')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->emptyStateIcon('heroicon-o-user-group')
            ->emptyStateHeading('No team members yet')
            ->emptyStateDescription('Add the people who work your shows and fulfillment workflow. Payroll is handled in the weekly Pay Run.')
            ->emptyStateActions([
                \Filament\Actions\CreateAction::make()->label('Add your first team member'),
            ])
            ->filters([
                SelectFilter::make('status')->options(Streamer::statusLabels()),
                SelectFilter::make('member_type')->label('Role')->options(Streamer::memberTypeLabels()),
                SelectFilter::make('whatnot_channel_id')->label('Channel')->relationship('channel', 'name'),
            ])
            ->actions([
                ViewAction::make()->size('sm')->iconButton(),
                EditAction::make()->size('sm')->iconButton(),
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
                            $blocked = $records->count() - $deletable->count();
                            $deletable->each->delete();

                            if ($blocked > 0) {
                                Notification::make()
                                    ->title($deletable->count() . ' team member(s) deleted')
                                    ->body("{$blocked} skipped — still have shows, payouts, loans, surcharges, or log entries.")
                                    ->warning()
                                    ->send();
                            } else {
                                Notification::make()->title($deletable->count() . ' team member(s) deleted')->success()->send();
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
        return [LoansRelationManager::class];
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
