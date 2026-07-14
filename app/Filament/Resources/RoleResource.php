<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RoleResource\Pages;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static ?string $navigationLabel = 'Roles & Permissions';

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-shield-check';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Settings';
    }

    public static function getNavigationSort(): ?int
    {
        return 5;
    }

    // Managing roles is sensitive — owner only.
    public static function canAccess(): bool
    {
        return auth()->user()?->isOwner() ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->isOwner() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Role')->columns(2)->schema([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->helperText('e.g. admin, manager, streamer, viewer'),
                TextInput::make('guard_name')
                    ->default('web')
                    ->required()
                    ->maxLength(255),
            ]),
            Section::make('Page Visibility')
                ->description('Check pages to hide from this role — hidden pages disappear from the sidebar and become inaccessible for that role. The owner always sees everything, and if a user has another role that shows a page it stays visible.')
                ->schema([
                    CheckboxList::make('hidden_pages')
                        ->label('Pages hidden from this role')
                        ->options(fn (): array => static::pageOptions())
                        ->columns(3)
                        ->searchable()
                        ->bulkToggleable()
                        ->dehydrated(false),  // stored in a setting, not on the roles table
                ]),
            Section::make('Permissions')
                ->description('Spatie permissions granted to this role (optional).')
                ->collapsed()
                ->schema([
                    CheckboxList::make('permissions')
                        ->relationship('permissions', 'name')
                        ->searchable()
                        ->bulkToggleable()
                        ->columns(3)
                        ->noSearchResultsMessage('No permissions defined yet.'),
                ]),
        ]);
    }

    /**
     * Navigable resources + pages, keyed by class => label, for the hide list.
     *
     * @return array<class-string, string>
     */
    public static function pageOptions(): array
    {
        $panel = Filament::getCurrentPanel() ?? Filament::getDefaultPanel();
        $opts  = [];

        foreach ($panel->getResources() as $resource) {
            if ($resource === static::class) {
                continue; // never let a role hide the roles manager itself
            }
            try { $opts[$resource] = $resource::getNavigationLabel(); } catch (\Throwable) {}
        }
        foreach ($panel->getPages() as $page) {
            try { $opts[$page] = $page::getNavigationLabel(); } catch (\Throwable) {}
        }

        asort($opts);

        return $opts;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('permissions_count')
                    ->counts('permissions')
                    ->label('Permissions')
                    ->badge()
                    ->color('info'),
                TextColumn::make('users_count')
                    ->counts('users')
                    ->label('Users')
                    ->badge(),
                TextColumn::make('guard_name')
                    ->label('Guard')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'edit'   => Pages\EditRole::route('/{record}/edit'),
        ];
    }
}
