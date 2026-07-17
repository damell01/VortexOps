<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RoleResource\Pages;
use Filament\Facades\Filament;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    /** Default roles the app assumes exist — protected from deletion. */
    public const CORE_ROLES = ['admin', 'super_admin', 'streamer', 'fulfillment', 'fulfillment_admin'];

    public static function isCoreRole(string $name): bool
    {
        return in_array($name, self::CORE_ROLES, true);
    }

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
            Section::make('Page Access')
                ->description('One row per page, grouped by section — Visible controls whether it shows up in this role\'s sidebar at all; Can Edit controls whether create/edit/delete actions work there (uncheck it alone to leave a page visible but view-only). "Can Edit" is currently enforced on the Fulfillment Center, with more pages adopting the same check over time. The owner always sees and can edit everything, and any other role a user holds that grants access wins.')
                ->schema(static::pageAccessSchema()),
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

    /**
     * Same universe of pages as pageOptions(), grouped by navigation group and
     * sorted within each group — lets the Page Access matrix render one
     * collapsible section per area instead of two long alphabetical walls
     * that are hard to cross-reference against each other.
     *
     * @return array<string, array<class-string, string>>
     */
    public static function pagesByGroup(): array
    {
        $panel  = Filament::getCurrentPanel() ?? Filament::getDefaultPanel();
        $groups = [];

        $add = function (string $class) use (&$groups) {
            if ($class === static::class) {
                return; // never let a role hide the roles manager itself
            }

            try {
                $group = $class::getNavigationGroup();
                $groupLabel = match (true) {
                    is_string($group) && $group !== '' => $group,
                    $group instanceof \UnitEnum => method_exists($group, 'getLabel') ? ($group->getLabel() ?? $group->name) : $group->name,
                    default => 'General',
                };
                $groups[$groupLabel][$class] = $class::getNavigationLabel();
            } catch (\Throwable) {}
        };

        foreach ($panel->getResources() as $resource) {
            $add($resource);
        }
        foreach ($panel->getPages() as $page) {
            $add($page);
        }

        foreach ($groups as &$pages) {
            asort($pages);
        }
        unset($pages);
        ksort($groups);

        return $groups;
    }

    /** A page's fully-qualified class name, sanitized into a safe Livewire form-array key. */
    public static function pageKey(string $class): string
    {
        return str_replace('\\', '__', $class);
    }

    /**
     * Build the page_perms form state from the flat hidden/readonly class
     * lists NavVisibility stores — used to pre-fill the edit form.
     *
     * @param array<int,string> $hidden
     * @param array<int,string> $readonly
     * @return array<string, array{visible: bool, editable: bool}>
     */
    public static function pagePermsFormState(array $hidden, array $readonly): array
    {
        $state = [];

        foreach (static::pageOptions() as $class => $label) {
            $key = static::pageKey($class);
            $state[$key] = [
                'visible'  => ! in_array($class, $hidden, true),
                'editable' => ! in_array($class, $readonly, true),
            ];
        }

        return $state;
    }

    /**
     * Reverse of pagePermsFormState() — turn the submitted page_perms array
     * back into the flat hidden/readonly class lists NavVisibility expects.
     *
     * @param array<string, array{visible?: bool, editable?: bool}> $pagePerms
     * @return array{0: array<int,string>, 1: array<int,string>} [$hidden, $readonly]
     */
    public static function pagePermsToLists(array $pagePerms): array
    {
        $hidden   = [];
        $readonly = [];

        foreach (static::pageOptions() as $class => $label) {
            $key   = static::pageKey($class);
            $entry = $pagePerms[$key] ?? [];

            if (empty($entry['visible'] ?? true)) {
                $hidden[] = $class;
            }
            if (empty($entry['editable'] ?? true)) {
                $readonly[] = $class;
            }
        }

        return [$hidden, $readonly];
    }

    /** @return array<int, Section> */
    protected static function pageAccessSchema(): array
    {
        $sections = [];

        foreach (static::pagesByGroup() as $group => $pages) {
            $rows = [];

            foreach ($pages as $class => $label) {
                $key = static::pageKey($class);

                $rows[] = Grid::make(12)->schema([
                    Placeholder::make("page_label_{$key}")
                        ->hiddenLabel()
                        ->content($label)
                        ->columnSpan(6),
                    Checkbox::make("page_perms.{$key}.visible")
                        ->label('Visible')
                        ->default(true)
                        ->dehydrated(false) // stored in a setting, not on the roles table
                        ->columnSpan(3),
                    Checkbox::make("page_perms.{$key}.editable")
                        ->label('Can Edit')
                        ->default(true)
                        ->dehydrated(false) // stored in a setting, not on the roles table
                        ->columnSpan(3),
                ]);
            }

            $sections[] = Section::make($group)
                ->compact()
                ->collapsible()
                ->collapsed()
                ->schema($rows);
        }

        return $sections;
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
            ->defaultSort('name')
            ->actions([
                \Filament\Actions\EditAction::make()->iconButton(),
                \Filament\Actions\DeleteAction::make()->iconButton()
                    ->visible(fn (Role $record) => ! static::isCoreRole($record->name)),
            ]);
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
