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
use Filament\Actions\Action;
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
    public static function shouldRegisterNavigation(): bool
    {
        // People are managed from Users. Keep this resource/routes available
        // for the detailed operational workspace without a duplicate Team tab.
        return false;
    }

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
        $query = parent::getEloquentQuery()->with('business')->withCount('inventoryLocations');

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
                ->compact()
                ->schema([
                    Grid::make(['default' => 2, 'lg' => 4])->schema([
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

            \App\Filament\Support\FormFlow::tabs('member', static::formParts()),
        ]);
    }

    /**
     * The team-member form in parts: tabs when editing (Profile, Pay, ...),
     * steps when adding someone (see CreateStreamer).
     */
    public static function formParts(): array
    {
        return [
            ['key' => 'profile', 'label' => 'Profile', 'icon' => 'heroicon-o-user', 'step' => 'Who they are',
                'schema' => [
                    Grid::make(['default' => 1, 'sm' => 2, 'lg' => 3])->schema([
                        TextInput::make('name')->required()->maxLength(255),
                        TextInput::make('legal_name')->maxLength(255),
                        TextInput::make('email')->email()->maxLength(255),
                        TextInput::make('phone')->tel()->maxLength(50),
                        Select::make('member_type')
                            ->label('Role')
                            ->helperText('Where they appear in the operations workflow.')
                            ->options(Streamer::memberTypeLabels())
                            ->required()
                            ->default('streamer')
                            ->native(false),
                        Select::make('status')
                            ->options(Streamer::statusLabels())
                            ->required()
                            ->default('active')
                            ->native(false),
                        Select::make('streamer_type')->label('Streamer Type')->options(Streamer::streamerTypeLabels())->default('in_house')->required()->native(false)->helperText('Remote streamers use the same show/payroll workflow but can be filtered separately.'),
                        Select::make('whatnot_channel_id')
                            ->label('Channel')
                            ->relationship('channel', 'name')
                            ->searchable()
                            ->preload()
                            ->helperText('Main channel for their stats.'),
                        Select::make('business_id')
                            ->label('Business')
                            ->relationship('business', 'name')
                            ->default(fn () => \App\Support\BusinessContext::currentId())
                            ->placeholder('Not assigned')
                            ->preload()
                            ->helperText('Who they work for: decides which rates and Pay Runs they\'re paid in.'),
                    ]),
                    Textarea::make('notes')->rows(3)->columnSpanFull(),
                ]],

            ['key' => 'pay', 'label' => 'Pay', 'icon' => 'heroicon-o-banknotes', 'step' => 'How they are paid',
                'intro' => 'Their normal pay. Pay Runs keep the amount they worked out, so changing a rate here never rewrites past pay.',
                'schema' => [
                    Grid::make(['default' => 1, 'sm' => 2, 'lg' => 3])->schema([
                        Select::make('payout_type')->label('Pay Type')->options(Streamer::assignablePayoutTypeLabels())->native(false),
                        TextInput::make('hourly_rate')->label('Hourly Rate')->numeric()->prefix('$')->suffix('/hr'),
                        TextInput::make('payout_percentage')->label('Payout %')->numeric()->suffix('%'),
                        TextInput::make('package_rate')->label('Package / Flat Rate')->numeric()->prefix('$'),
                        Toggle::make('include_tips')->label('Include Tips')->default(true)->inline(false),
                    ]),
                ]],

            ['key' => 'routing', 'label' => 'Bank routing', 'icon' => 'heroicon-o-arrows-right-left', 'step' => 'Optional',
                'intro' => 'Send pay from a channel to a specific bank account. Leave empty if everything goes to one account.',
                'schema' => [
                    Repeater::make('channel_routing_rules')
                        ->hiddenLabel()
                        ->schema([
                            TextInput::make('channel')->label('Channel Name')->placeholder('e.g. Breaks')->required()->maxLength(100),
                            TextInput::make('bank_label')->label('Bank / Account Label')->placeholder('e.g. Chase Business x1234')->required()->maxLength(255),
                        ])
                        ->columns(['default' => 1, 'sm' => 2])
                        ->reorderable()
                        ->cloneable()
                        ->addActionLabel('Add routing rule')
                        ->itemLabel(fn (array $state): ?string => ($state['channel'] ?? null)
                            ? ($state['channel'] . ' → ' . ($state['bank_label'] ?? '?'))
                            : null)
                        ->collapsible()
                        ->columnSpanFull()
                        ->defaultItems(0),
                ]],

            ['key' => 'login', 'label' => 'Login', 'icon' => 'heroicon-o-key', 'step' => 'Optional',
                'intro' => 'Give them a sign-in so they can see their own shows, pay and inventory.',
                'visible' => fn (string $operation): bool => $operation === 'create',
                'schema' => [
                    Grid::make(['default' => 1, 'sm' => 2, 'lg' => 3])->schema([
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
                ]],

            // Read-only. This was a repeater saved through inventoryLocations(),
            // which also returns the business's shared storage: every streamer
            // failed to save on it ("The selected location is invalid" — its
            // rows held a location's name, its options were keyed by id), and
            // had it saved, removing a row would have deleted shared locations.
            // Who can use a location is set on the location itself now.
            ['key' => 'inventory', 'label' => 'Inventory', 'icon' => 'heroicon-o-archive-box',
                'visible' => fn (?Streamer $record): bool => (bool) $record?->exists,
                'schema' => [
                    Placeholder::make('inventory_access')
                        ->label('Locations they pick stock from')
                        ->content(function (?Streamer $record): \Illuminate\Support\HtmlString {
                            $names = $record ? $record->inventoryLocations()->orderBy('name')->pluck('name') : collect();
                            $link = InventoryLocationResource::getUrl('index');

                            return new \Illuminate\Support\HtmlString(
                                ($names->isEmpty() ? 'No locations yet.' : e($names->join(', ')))
                                . ' <a href="' . e($link) . '" class="text-primary-600 underline">Manage locations</a>'
                            );
                        }),
                ]],
        ];
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
                TextColumn::make('business.name')->label('Business')->badge()->default('Not assigned')->color(fn (Streamer $record) => $record->business_id ? 'primary' : 'gray')->toggleable(),
                TextColumn::make('streamer_type')->label('Type')->badge()->formatStateUsing(fn ($state) => Streamer::streamerTypeLabels()[$state] ?? 'In-House')->color(fn ($state) => $state === 'remote' ? 'info' : 'gray'),
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
                SelectFilter::make('streamer_type')->label('Streamer Type')->options(Streamer::streamerTypeLabels()),
                SelectFilter::make('whatnot_channel_id')->label('Channel')->relationship('channel', 'name'),
                SelectFilter::make('business_id')->label('Business')->relationship('business', 'name'),
            ])
            ->actions([
                Action::make('analytics')
                    ->label('Analytics')
                    ->icon('heroicon-o-chart-bar')
                    ->iconButton()
                    ->tooltip('View analytics')
                    ->visible(fn () => \App\Filament\Pages\StreamerAnalytics::canAccess())
                    ->url(fn (Streamer $record) => \App\Filament\Pages\StreamerAnalytics::getUrl(['streamer' => $record->id])),
                Action::make('statement')
                    ->label('Statement')
                    ->icon('heroicon-o-document-text')
                    ->iconButton()
                    ->tooltip('View statement')
                    ->visible(fn () => \App\Filament\Pages\StreamerStatement::canAccess())
                    ->url(fn (Streamer $record) => \App\Filament\Pages\StreamerStatement::getUrl(['streamer' => $record->id])),
                \App\Filament\Actions\SetUpStreamerInventoryAction::make()
                    ->iconButton()
                    ->tooltip(fn (Streamer $record) => app(\App\Services\StreamerInventorySetup::class)->ownLocation($record) ? 'Add inventory' : 'Set up inventory'),
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
                    // Their own location for everyone selected who doesn't have one.
                    \Filament\Actions\BulkAction::make('setUpInventory')
                        ->label('Set up inventory')
                        ->icon('heroicon-o-archive-box-arrow-down')
                        ->visible(fn () => auth()->user()?->isAdmin() ?? false)
                        ->modalHeading('Set up inventory for the selected streamers')
                        ->modalDescription('Each gets their own location ("Name\'s Inventory"). Anyone who already has one keeps it.')
                        ->modalWidth('2xl')
                        ->modalSubmitActionLabel('Set up')
                        ->schema(\App\Filament\Actions\SetUpStreamerInventoryAction::stockEachFields())
                        ->action(fn (Collection $records, array $data) => \App\Filament\Actions\SetUpStreamerInventoryAction::runMany($records, $data))
                        ->deselectRecordsAfterCompletion(),
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
            ->paginationPageOptions(\App\Support\MobileTablePagination::options([10, 25, 50]))
            ->defaultPaginationPageOption(\App\Support\MobileTablePagination::defaultPageSize(25))
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