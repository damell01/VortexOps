<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use App\Models\Streamer;
use App\Support\UserPayFields;
use Filament\Schemas\Components\Utilities\Get;
use Spatie\Permission\Models\Role;
use App\Support\Impersonation;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TagsInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\HtmlString;
use App\Filament\Concerns\HasAdminNavVisibility;

class UserResource extends Resource
{
    use HasAdminNavVisibility;

    /** Roles only the owner may grant/revoke. */
    public const PRIVILEGED_ROLES = ['admin', 'super_admin', 'fulfillment_admin'];

    /**
     * Roles nobody may grant from the UI — not even the owner. The owner
     * account's powers come from the email check (User::isOwner()), so no
     * new super admins should ever be minted here; existing holders are
     * left untouched.
     */
    public const UNGRANTABLE_ROLES = ['super_admin'];

    protected static ?string $model = User::class;

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'email'];
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Admin';
    }

    public static function getNavigationSort(): ?int
    {
        return 2;
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-users';
    }

    public static function getModelLabel(): string
    {
        return 'User';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Users';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['roles', 'streamer']);
    }

    public static function canAccess(): bool
    {
        // An explicit grant on Roles & Permissions is the answer; the rules
        // below are the fallback for roles that have no explicit list.
        if (\App\Support\RoleAccess::grants(static::class)) {
            return true;
        }

        $user = auth()->user();
        return ($user?->isAdmin() || $user?->isOwner()) ?? false;
    }

    /**
     * Never yourself, never the owner account, and privileged-role holders
     * (admin/super_admin/fulfillment_admin) only by the owner — mirrors the
     * role-escalation protection in EditUser/CreateUser: if only the owner
     * can grant a privileged role, only the owner should be able to remove
     * the account holding one.
     */
    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        $user = auth()->user();

        if (! $user?->isAdmin()) {
            return false;
        }
        if ($record->id === $user->id) {
            return false;
        }
        if ($record->isOwner()) {
            return false;
        }
        if ($record->getRoleNames()->intersect(self::PRIVILEGED_ROLES)->isNotEmpty()) {
            return $user->isOwner();
        }

        return true;
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    /** 'streamer', 'fulfillment' or null, from the roles ticked on the form. */
    public static function payMemberType(Get $get): ?string
    {
        $roleIds = collect($get('roles') ?? [])->filter();
        if ($roleIds->isEmpty()) {
            return null;
        }
        $names = once(fn () => Role::query()->pluck('name', 'id'))->only($roleIds->all())->values();

        return $names->contains('streamer') ? 'streamer' : ($names->contains('fulfillment') ? 'fulfillment' : null);
    }

    /** The streamer pay model the Pay section is showing: chosen, or the business's default. */
    public static function payModel(Get $get): string
    {
        return $get(UserPayFields::MODEL) ?: UserPayFields::defaultModel($get(UserPayFields::BUSINESS));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Account Details')->columns(2)->columnSpanFull()->schema([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),

                TextInput::make('email')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),


                TextInput::make('password')
                    ->password()
                    ->revealable()
                    ->minLength(8)
                    ->maxLength(255)
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->dehydrateStateUsing(fn (string $state): string => Hash::make($state))
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->helperText(fn (string $operation): string => $operation === 'edit'
                        ? 'Leave blank to keep the current password.'
                        : '')
                    ->columnSpanFull(),
            ]),

            Section::make('Roles & Access')->columnSpanFull()->schema([
                Select::make('roles')
                    ->multiple()
                    ->live()
                    // Options come from the relationship (keyed by id, labelled by
                    // name). Only the owner sees the privileged roles, so other
                    // admins can manage non-privileged roles (e.g. streamer) but
                    // cannot create more admins. super_admin is never offered to
                    // anyone. Enforced again on save below.
                    ->relationship('roles', 'name', modifyQueryUsing: function ($query) {
                        $query->whereNotIn('name', auth()->user()?->isOwner() ? UserResource::UNGRANTABLE_ROLES : UserResource::PRIVILEGED_ROLES);
                    })
                    ->getOptionLabelFromRecordUsing(fn ($record): string => match ($record->name) {
                        'admin' => 'Admin',
                        'fulfillment_admin' => 'Fulfillment Admin',
                        'fulfillment' => 'Fulfillment',
                        'streamer' => 'Streamer',
                        default => str($record->name)->replace('_', ' ')->title()->toString(),
                    })
                    ->preload()
                    ->helperText('Admin — full access. Streamer — scoped to their own inventory locations. Fulfillment — scoped to their assigned shows. Fulfillment Admin — sees every channel\'s fulfillment work.'),

                TagsInput::make('streamer_aliases')
                    ->label('Streamer Aliases')
                    ->placeholder('Type an alias, then press Enter')
                    ->splitKeys(['Tab', 'Enter'])
                    ->nestedRecursiveRules(['string', 'max:255'])
                    ->afterContent(new HtmlString('<button type="button" class="mt-2 inline-flex min-h-10 items-center justify-center rounded-lg bg-primary-600 px-4 text-sm font-semibold text-white shadow-sm hover:bg-primary-500" onclick="const field=this.closest(\'[data-field-wrapper]\')||this.parentElement;const input=field?.querySelector(\'input\');if(input&&input.value.trim()){input.dispatchEvent(new KeyboardEvent(\'keydown\',{key:\'Enter\',code:\'Enter\',keyCode:13,which:13,bubbles:true}));input.focus();}">+ Add alias</button>'))
                    ->dehydrated(false)
                    ->visible(function (Get $get): bool {
                        $roleIds = collect($get('roles') ?? [])->filter();
                        return $roleIds->isNotEmpty()
                            && Role::query()->whereIn('id', $roleIds)->where('name', 'streamer')->exists();
                    })
                    ->helperText('Type a handle or show name, then tap Add alias or press Enter/Tab. Each added alias appears as a removable tag and is used to match imported shows automatically.'),

                \Filament\Forms\Components\Toggle::make('inventory_location_override_enabled')
                    ->label('Override inventory locations for this streamer')
                    ->live()
                    ->dehydrated(false)
                    ->visible(fn (?User $record): bool => (bool) $record?->isStreamer())
                    ->helperText('Off uses global streamer locations. On replaces that list with the locations chosen below.'),
                Select::make('inventory_location_override_ids')
                    ->label('Allowed inventory locations')
                    ->multiple()->searchable()->preload()->native(false)
                    ->options(fn () => \App\Support\InventoryVisibility::selectableLocations())
                    ->dehydrated(false)
                    ->visible(fn (Get $get, ?User $record): bool => (bool) $record?->isStreamer() && (bool) $get('inventory_location_override_enabled'))
                    ->helperText('Choose only this person’s location to restrict them to it. An empty selection allows no locations.'),

                Select::make('streamer_type')
                    ->label('Streamer Type')
                    ->options(Streamer::streamerTypeLabels())
                    ->default('in_house')
                    ->native(false)
                    ->live()
                    ->dehydrated(false)
                    ->visible(function (Get $get): bool {
                        $roleIds = collect($get('roles') ?? [])->filter();
                        return $roleIds->isNotEmpty()
                            && Role::query()->whereIn('id', $roleIds)->where('name', 'streamer')->exists();
                    })
                    ->helperText('In-House works on site. Remote uses the same show and payroll workflow but is identified separately.'),

                Select::make('streamer_id')
                    ->label('Linked Streamer Profile')
                    ->relationship('streamer', 'name')
                    ->searchable()
                    ->preload()
                    ->nullable()
                    ->visible(fn (string $operation): bool => $operation === 'edit' && false)
                    ->helperText('Normally created and linked automatically when the Streamer role is assigned. Use this only to repair an existing account.'),
            ]),

            Section::make('Pay')
                ->description('This person\'s pay. Leave a box blank to use the team default from Rates & Adjustments; fill it in to set a different amount for this person.')
                ->columns(2)->columnSpanFull()
                ->visible(fn (Get $get): bool => static::payMemberType($get) !== null)
                ->schema([
                    Select::make(UserPayFields::BUSINESS)
                        ->label('Business')
                        ->options(fn () => \App\Models\Business::ordered()->pluck('name', 'id')->all())
                        ->placeholder('Not assigned')
                        ->default(fn () => \App\Support\BusinessContext::currentId())
                        ->helperText('Who this person works for. Their pay follows that business\'s rates, and they\'re paid in its Pay Runs.')
                        ->native(false)
                        ->live()
                        ->dehydrated(false),

                    Select::make(UserPayFields::MODEL)
                        ->label('How they\'re paid')
                        ->options(['profit_share' => 'Profit share (+ hourly if in-house)', 'sales_split' => 'Sales split (the business keeps a %)', 'shift_share' => 'Hourly + shipments + profit share'])
                        ->placeholder(fn (Get $get) => 'Business default: '.match (UserPayFields::defaultModel($get(UserPayFields::BUSINESS))) { 'sales_split' => 'Sales split', 'shift_share' => 'Hourly + shipments + profit share', default => 'Profit share' })
                        ->helperText(fn (Get $get): ?string => static::payModel($get) === 'shift_share'
                            ? 'Hours × hourly rate + envelopes and packages at their rates + share % of (net sales − product cost − burden − hourly − shipment pay).'
                            : null)
                        ->native(false)
                        ->live()
                        ->dehydrated(false)
                        ->visible(fn (Get $get): bool => static::payMemberType($get) === 'streamer'),

                    TextInput::make(UserPayFields::key('payout_percentage'))
                        ->label('Streamer pay %')
                        ->numeric()->minValue(0)->maxValue(100)->step(0.01)->suffix('%')
                        ->placeholder(fn (Get $get) => UserPayFields::defaultHint('streamer', 'payout_percentage', $get(UserPayFields::BUSINESS)))
                        ->dehydrated(false)
                        ->visible(fn (Get $get): bool => static::payMemberType($get) === 'streamer' && in_array(static::payModel($get), ['profit_share', 'shift_share'], true)),

                    TextInput::make(UserPayFields::key('house_cut_percentage'))
                        ->label('Business keeps')
                        ->numeric()->minValue(0)->maxValue(100)->step(0.01)->suffix('%')
                        ->placeholder(fn (Get $get) => UserPayFields::defaultHint('streamer', 'house_cut_percentage', $get(UserPayFields::BUSINESS)))
                        ->helperText('Of what their shows earn; they get the rest.')
                        ->dehydrated(false)
                        ->visible(fn (Get $get): bool => static::payMemberType($get) === 'streamer' && static::payModel($get) === 'sales_split'),

                    Select::make(UserPayFields::key('payout_type'))
                        ->label('Pay type')
                        ->options(Streamer::assignablePayoutTypeLabels())
                        ->placeholder(fn (Get $get) => UserPayFields::defaultHint('fulfillment', 'payout_type', $get(UserPayFields::BUSINESS)))
                        ->native(false)
                        ->dehydrated(false)
                        ->visible(fn (Get $get): bool => static::payMemberType($get) === 'fulfillment'),

                    TextInput::make(UserPayFields::key('hourly_rate'))
                        ->label('Hourly rate')
                        ->numeric()->minValue(0)->step(0.01)->prefix('$')->suffix('/hr')
                        ->placeholder(fn (Get $get) => UserPayFields::defaultHint(static::payMemberType($get) ?? 'streamer', 'hourly_rate', $get(UserPayFields::BUSINESS)))
                        ->helperText(fn (Get $get): ?string => static::payMemberType($get) === 'streamer'
                            ? ($get('streamer_type') === 'remote'
                                ? 'Remote streamers are not paid hourly. Set Streamer Type to In-House to pay this.'
                                : 'Paid for each show\'s hours on top of their share.')
                            : null)
                        ->dehydrated(false),

                    TextInput::make(UserPayFields::key('pwe_rate'))
                        ->label(fn (Get $get) => static::payMemberType($get) === 'fulfillment' ? 'PWE rate' : 'Pay per envelope (PWE)')
                        ->numeric()->minValue(0)->step(0.01)->prefix('$')
                        ->placeholder(fn (Get $get) => UserPayFields::defaultHint(static::payMemberType($get) ?? 'streamer', 'pwe_rate', $get(UserPayFields::BUSINESS)))
                        ->dehydrated(false)
                        ->visible(fn (Get $get): bool => static::payMemberType($get) === 'fulfillment' || static::payModel($get) === 'shift_share'),

                    TextInput::make(UserPayFields::key('label_rate'))
                        ->label(fn (Get $get) => static::payMemberType($get) === 'fulfillment' ? 'Label rate' : 'Pay per package')
                        ->numeric()->minValue(0)->step(0.01)->prefix('$')
                        ->placeholder(fn (Get $get) => UserPayFields::defaultHint(static::payMemberType($get) ?? 'streamer', 'label_rate', $get(UserPayFields::BUSINESS)))
                        ->dehydrated(false)
                        ->visible(fn (Get $get): bool => static::payMemberType($get) === 'fulfillment' || static::payModel($get) === 'shift_share'),

                    TextInput::make(UserPayFields::key('burden_rate_value'))
                        ->label('Burden per show')
                        ->numeric()->minValue(0)->step(0.01)->prefix('$')
                        ->placeholder('None')
                        ->helperText('Taken off the show before the profit share. Leave blank for none.')
                        ->dehydrated(false)
                        ->visible(fn (Get $get): bool => static::payMemberType($get) === 'streamer' && static::payModel($get) === 'shift_share'),

                    TextInput::make(UserPayFields::key('monthly_burden_addback'))
                        ->label('Monthly burden add-back — amount per payday')
                        ->numeric()->minValue(0)->step(0.01)->prefix('
                        ->label('Flat / package rate')
                        ->numeric()->minValue(0)->step(0.01)->prefix('$')
                        ->placeholder(fn (Get $get) => UserPayFields::defaultHint('fulfillment', 'package_rate', $get(UserPayFields::BUSINESS)))
                        ->dehydrated(false)
                        ->visible(fn (Get $get): bool => static::payMemberType($get) === 'fulfillment'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->emptyStateHeading('No team members yet')
            ->emptyStateDescription('Add a user and assign the role that matches their job.')
            ->emptyStateIcon('heroicon-o-users')
            ->deferLoading()
            ->columns([
                TextColumn::make('name')
                    ->label('Team member')
                    ->description(fn (User $record): string => $record->email)
                    ->searchable(['name', 'email'])
                    ->sortable()
                    ->weight('semibold'),
                TextColumn::make('roles.name')
                    ->label('Role')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'super_admin' => 'warning',
                        'admin' => 'danger',
                        'fulfillment_admin' => 'success',
                        'fulfillment' => 'success',
                        'streamer' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => str($state)->replace('_', ' ')->title())
                    ->separator(', '),
                TextColumn::make('streamer.member_type')
                    ->label('Team profile')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state === 'fulfillment' ? 'Fulfillment' : ($state === 'streamer' ? 'Streamer' : '—'))
                    ->default('—')
                    ->placeholder('—'),
                TextColumn::make('streamer.streamer_type')
                    ->label('Streamer type')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state ? (Streamer::streamerTypeLabels()[$state] ?? $state) : '—')
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->label('Joined')
                    ->date('M j, Y')
                    ->description(fn (User $record): string => $record->created_at?->diffForHumans() ?? '')
                    ->sortable(),
            ])
            ->persistFiltersInSession()
            ->paginationPageOptions(\App\Support\MobileTablePagination::options([8, 16, 32]))
            ->defaultPaginationPageOption(\App\Support\MobileTablePagination::defaultPageSize(8))
            ->defaultSort('name')
            ->actions([
                static::impersonateAction(),
                ViewAction::make()->iconButton()->tooltip('View user'),
                EditAction::make()->iconButton()->tooltip('Edit user'),
                DeleteAction::make()->iconButton()->tooltip('Delete user')
                    ->visible(fn (User $record) => static::canDelete($record)),
            ])
            ->bulkActions([]);
    }

    /** Sign in as this user to see what they see; a banner brings you back. */
    public static function impersonateAction(): Action
    {
        return Action::make('impersonate')
            ->label('Log in as')
            ->icon('heroicon-o-arrow-right-end-on-rectangle')
            ->iconButton()
            ->tooltip(fn (User $record) => 'Log in as '.$record->name)
            ->color('gray')
            ->visible(fn (User $record) => Impersonation::canImpersonate(auth()->user(), $record))
            ->requiresConfirmation()
            ->modalHeading(fn (User $record) => 'Log in as '.$record->name.'?')
            ->modalDescription('You will see the app exactly as they do. A banner at the top of every page brings you back to your own account.')
            ->modalSubmitActionLabel('Log in as user')
            ->action(function (User $record) {
                Impersonation::start(auth()->user(), $record);

                return redirect()->to('/admin');
            });
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'view'   => Pages\ViewUser::route('/{record}'),
            'edit'   => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
)
                        ->helperText('Added to pay once per approved payday, not per show. Leave blank to disable.')
                        ->dehydrated(false)
                        ->visible(fn (Get $get): bool => static::payMemberType($get) === 'streamer' && static::payModel($get) === 'shift_share'),

                    TextInput::make(UserPayFields::key('monthly_burden_payments'))
                        ->label('Maximum burden payments per calendar month')
                        ->numeric()->integer()->minValue(1)->maxValue(5)
                        ->placeholder('4')
                        ->helperText('Maximum number of approved add-backs per month. Admin confirms each pay run.')
                        ->dehydrated(false)
                        ->visible(fn (Get $get): bool => static::payMemberType($get) === 'streamer' && static::payModel($get) === 'shift_share'),

                    TextInput::make(UserPayFields::key('package_rate'))
                        ->label('Flat / package rate')
                        ->numeric()->minValue(0)->step(0.01)->prefix('$')
                        ->placeholder(fn (Get $get) => UserPayFields::defaultHint('fulfillment', 'package_rate', $get(UserPayFields::BUSINESS)))
                        ->dehydrated(false)
                        ->visible(fn (Get $get): bool => static::payMemberType($get) === 'fulfillment'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->emptyStateHeading('No team members yet')
            ->emptyStateDescription('Add a user and assign the role that matches their job.')
            ->emptyStateIcon('heroicon-o-users')
            ->deferLoading()
            ->columns([
                TextColumn::make('name')
                    ->label('Team member')
                    ->description(fn (User $record): string => $record->email)
                    ->searchable(['name', 'email'])
                    ->sortable()
                    ->weight('semibold'),
                TextColumn::make('roles.name')
                    ->label('Role')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'super_admin' => 'warning',
                        'admin' => 'danger',
                        'fulfillment_admin' => 'success',
                        'fulfillment' => 'success',
                        'streamer' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => str($state)->replace('_', ' ')->title())
                    ->separator(', '),
                TextColumn::make('streamer.member_type')
                    ->label('Team profile')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state === 'fulfillment' ? 'Fulfillment' : ($state === 'streamer' ? 'Streamer' : '—'))
                    ->default('—')
                    ->placeholder('—'),
                TextColumn::make('streamer.streamer_type')
                    ->label('Streamer type')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state ? (Streamer::streamerTypeLabels()[$state] ?? $state) : '—')
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->label('Joined')
                    ->date('M j, Y')
                    ->description(fn (User $record): string => $record->created_at?->diffForHumans() ?? '')
                    ->sortable(),
            ])
            ->persistFiltersInSession()
            ->paginationPageOptions(\App\Support\MobileTablePagination::options([8, 16, 32]))
            ->defaultPaginationPageOption(\App\Support\MobileTablePagination::defaultPageSize(8))
            ->defaultSort('name')
            ->actions([
                static::impersonateAction(),
                ViewAction::make()->iconButton()->tooltip('View user'),
                EditAction::make()->iconButton()->tooltip('Edit user'),
                DeleteAction::make()->iconButton()->tooltip('Delete user')
                    ->visible(fn (User $record) => static::canDelete($record)),
            ])
            ->bulkActions([]);
    }

    /** Sign in as this user to see what they see; a banner brings you back. */
    public static function impersonateAction(): Action
    {
        return Action::make('impersonate')
            ->label('Log in as')
            ->icon('heroicon-o-arrow-right-end-on-rectangle')
            ->iconButton()
            ->tooltip(fn (User $record) => 'Log in as '.$record->name)
            ->color('gray')
            ->visible(fn (User $record) => Impersonation::canImpersonate(auth()->user(), $record))
            ->requiresConfirmation()
            ->modalHeading(fn (User $record) => 'Log in as '.$record->name.'?')
            ->modalDescription('You will see the app exactly as they do. A banner at the top of every page brings you back to your own account.')
            ->modalSubmitActionLabel('Log in as user')
            ->action(function (User $record) {
                Impersonation::start(auth()->user(), $record);

                return redirect()->to('/admin');
            });
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'view'   => Pages\ViewUser::route('/{record}'),
            'edit'   => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
