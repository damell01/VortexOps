<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use DateTimeZone;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;
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
        return 'Settings';
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

    private static function getTimezoneOptions(): array
    {
        $timezones = DateTimeZone::listIdentifiers();
        return array_combine($timezones, $timezones);
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

                Select::make('timezone')
                    ->label('Timezone')
                    ->options(static::getTimezoneOptions())
                    ->default('UTC')
                    ->searchable(),

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
                    // Options come from the relationship (keyed by id, labelled by
                    // name). Only the owner sees the privileged roles, so other
                    // admins can manage non-privileged roles (e.g. streamer) but
                    // cannot create more admins. super_admin is never offered to
                    // anyone. Enforced again on save below.
                    ->relationship('roles', 'name', modifyQueryUsing: function ($query) {
                        $query->whereNotIn('name', UserResource::UNGRANTABLE_ROLES);
                        if (! (auth()->user()?->isOwner() ?? false)) {
                            $query->whereNotIn('name', UserResource::PRIVILEGED_ROLES);
                        }
                    })
                    ->preload()
                    ->helperText('Admin — full access. Streamer — scoped to their own inventory locations. Fulfillment — scoped to their assigned shows. Fulfillment Admin — sees every channel\'s fulfillment work.'),

                Select::make('streamer_id')
                    ->label('Linked Streamer Profile')
                    ->relationship('streamer', 'name')
                    ->searchable()
                    ->preload()
                    ->nullable()
                    ->helperText('Required when the user has the Streamer role.'),
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
                TextColumn::make('streamer.name')
                    ->label('Streamer profile')
                    ->default('Not linked')
                    ->placeholder('Not linked')
                    ->icon('heroicon-o-video-camera'),
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
                ViewAction::make()->iconButton()->tooltip('View user'),
                EditAction::make()->iconButton()->tooltip('Edit user'),
                DeleteAction::make()->iconButton()->tooltip('Delete user')
                    ->visible(fn (User $record) => static::canDelete($record)),
            ])
            ->bulkActions([]);
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
