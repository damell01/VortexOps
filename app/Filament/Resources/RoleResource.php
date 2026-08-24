<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RoleResource\Pages;
use App\Support\NavVisibility;
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
        // An explicit grant on Roles & Permissions is the answer; the rules
        // below are the fallback for roles that have no explicit list.
        if (\App\Support\RoleAccess::grants(static::class)) {
            return true;
        }

        return auth()->user()?->isOwner() ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        // A role granted this page on Roles & Permissions gets its link too;
        // access without a way to reach it is only half a grant.
        if (\App\Support\RoleAccess::grants(static::class)) {
            return true;
        }

        return auth()->user()?->isOwner() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Role')
                ->description('"admin" and "super_admin" get full access to every record automatically — that\'s built into the app. "streamer" and "fulfillment" are also built in: they always see only their own shows/payouts or only their assigned shows, no matter what you check below. A brand-new custom role name starts from scratch — it only sees what you explicitly grant it via Page Access and Permissions below, and any page whose data isn\'t already role-aware (most are admin/owner-only under the hood) will need code changes before a custom role can safely use it — ask if you want a specific page opened up.')
                ->columns(2)->columnSpanFull()->schema([
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
                ->description('One row per page, grouped by section. Visible controls whether the role gets the page at all — unchecking it removes the sidebar link and closes the URL, so it cannot be reached by typing the address either. Can Edit controls whether create/edit/delete actions work there (uncheck it alone to leave a page visible but view-only); that one is currently enforced on the Fulfillment Center, with more pages adopting it over time. Two tags explain rows that will not behave like a plain switch: "no sidebar link" means the page is opened from another page rather than the sidebar, so Visible cannot add a link there (unticking it still blocks the page); "code rule" means the page also enforces its own check in code, usually admin or owner, so hiding it works but showing it may not be enough on its own. The owner always sees and can edit everything, and where a user holds several roles, any role that grants access wins.')
                ->columnSpanFull()
                ->schema(static::pageAccessSchema()),
            Section::make('Permissions')
                ->description('Spatie permissions granted to this role (optional).')
                ->collapsed()
                ->columnSpanFull()
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
    /**
     * Panel plumbing rather than product pages. Every account needs these
     * whatever its role — the dashboard is where login lands, and the other
     * two are how a user manages their own profile and 2FA. Listing them gave
     * the owner switches that do nothing, which is the same lie as a setting
     * that is ignored.
     */
    /**
     * Defined on NavVisibility, which is what enforces it. Kept as one list
     * because the two have to agree exactly: a page this screen omits but the
     * gate does not exempt is denied to every role that saves, which is how
     * the dashboard — where login lands — started returning 403.
     */
    public const NOT_ROLE_CONTROLLED = NavVisibility::ALWAYS_AVAILABLE;

    /**
     * Every class a role's visibility can govern.
     *
     * Shared with the setup command and the allow-list migration so all three
     * agree on what "everything" means — a visible list built from a different
     * set than the one the Roles screen shows would grant or deny pages nobody
     * chose.
     *
     * @return array<int, class-string>
     */
    public static function roleControlledPages(): array
    {
        return array_keys(static::pageOptions());
    }

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
            if (in_array($page, self::NOT_ROLE_CONTROLLED, true)) {
                continue;
            }
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
        // Grouped from pageOptions() rather than by walking the panel again.
        //
        // These were two separate walks that each swallowed their own errors:
        // this one called getNavigationGroup() before getNavigationLabel(),
        // pageOptions() called only the latter. A class whose group threw was
        // dropped here but kept there, and vice versa — so the grid and the
        // list that is actually saved and enforced could disagree about which
        // pages exist.
        //
        // That disagreement is not cosmetic. pagePermsToLists() builds the
        // allow-list by iterating pageOptions(), so a page the grid rendered
        // ticked but pageOptions() had dropped was never written to it — and
        // the gate, reading a list that does not name the page, answers 403.
        // A page shown as Visible that refuses to open is exactly that gap.
        // One source now, so the two cannot drift.
        $groups = [];

        foreach (static::pageOptions() as $class => $label) {
            $groups[static::navigationGroupLabel($class)][$class] = $label;
        }

        foreach ($groups as &$pages) {
            asort($pages);
        }
        unset($pages);
        ksort($groups);

        return $groups;
    }

    /** A page's navigation group as a plain string, defaulting to General. */
    private static function navigationGroupLabel(string $class): string
    {
        try {
            $group = $class::getNavigationGroup();
        } catch (\Throwable) {
            // A group that cannot be resolved must not lose the row: being
            // ungrouped is a display problem, being absent is a 403.
            return 'General';
        }

        return match (true) {
            is_string($group) && $group !== '' => $group,
            $group instanceof \UnitEnum => method_exists($group, 'getLabel')
                ? ($group->getLabel() ?? $group->name)
                : $group->name,
            default => 'General',
        };
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
    /**
     * Tick state for the Page Access grid.
     *
     * Reads the role's visible list where it has one, rather than inferring
     * "visible" from "not hidden". Inferring it meant a page added since the
     * role was last saved appeared already ticked — while the role could not
     * actually see it — so the screen disagreed with itself.
     */
    public static function pagePermsFormState(string $role, array $readonly): array
    {
        $configured = NavVisibility::hasExplicitVisibility($role);
        $visible    = NavVisibility::visibleForRole($role);
        $hidden     = NavVisibility::hiddenForRole($role);

        $state = [];

        foreach (static::pageOptions() as $class => $label) {
            $key = static::pageKey($class);

            $state[$key] = [
                'visible'  => $configured
                    ? in_array($class, $visible, true)
                    : ! in_array($class, $hidden, true),
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
    /**
     * @return array{0: array<int,string>, 1: array<int,string>, 2: array<int,string>}
     *         [hidden, readonly, visible]
     */
    public static function pagePermsToLists(array $pagePerms): array
    {
        $hidden   = [];
        $readonly = [];
        $visible  = [];

        foreach (static::pageOptions() as $class => $label) {
            $key   = static::pageKey($class);
            $entry = $pagePerms[$key] ?? [];

            if (empty($entry['visible'] ?? true)) {
                $hidden[] = $class;
            } else {
                // The list that actually governs. Recorded explicitly so a page
                // added later is not granted to this role by simply not being
                // in the hidden list — which is how unticked pages kept showing
                // up after a release.
                $visible[] = $class;
            }

            if (empty($entry['editable'] ?? true)) {
                $readonly[] = $class;
            }
        }

        return [$hidden, $readonly, $visible];
    }

    /** @return array<int, Section> */
    /**
     * Whether a page enforces its own access rule in code, on top of whatever
     * is set here.
     *
     * Unchecking Visible always works — that is enforced centrally now. But
     * checking it does not necessarily grant the page: a class with its own
     * canAccess() typically insists on admin or owner, so a custom role stays
     * locked out and the checkbox reads as a lie. Surfacing that is the
     * difference between a setting that does nothing and one that says so.
     *
     * Detected by where the method body lives rather than by its declaring
     * class — a trait's methods report the *using* class as the declarer, so
     * reflection alone cannot tell an override from an inherited default.
     */
    public static function hasOwnAccessRule(string $class): bool
    {
        if (! method_exists($class, 'canAccess')) {
            return false;
        }

        $definedIn = function (string $method) use ($class): ?string {
            try {
                return (new \ReflectionMethod($class, $method))->getFileName() ?: null;
            } catch (\Throwable) {
                return null;
            }
        };

        $isVendor = fn (?string $file): bool => $file === null
            || str_contains($file, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR);

        $canAccess = $definedIn('canAccess');

        // Filament's own default restricts nothing.
        if ($isVendor($canAccess)) {
            return false;
        }

        // Reached through HasModuleAccess, whose canAccess() is module state
        // plus the visibility list plus passesModuleAccessCheck(). The first
        // two already have their own tags and their own rows, so the only
        // thing left that this screen cannot account for is an overridden
        // passesModuleAccessCheck(). Tagging the trait itself would put a
        // warning on 67 of 76 rows, which is the same as no warning at all.
        if ($canAccess === (new \ReflectionClass(\App\Filament\Concerns\HasModuleAccess::class))->getFileName()) {
            $check = $definedIn('passesModuleAccessCheck');

            return $check !== null
                && $check !== $canAccess
                && ! $isVendor($check);
        }

        // Its own canAccess(), written in this codebase — usually admin or
        // owner, and invisible on this screen until now. Comparing the
        // method's file against the class's own file, as this did, missed
        // every rule reached through a trait.
        return true;
    }

    /**
     * Whether a page can ever appear in the sidebar.
     *
     * Twenty-six pages are sub-pages opened from somewhere else and return
     * false from shouldRegisterNavigation() unconditionally. They were still
     * listed here with a Visible checkbox, so ticking one looked like it should
     * put a link in the sidebar and never did — which is most of why the
     * settings and the sidebar appeared not to match.
     *
     * Asked at runtime rather than parsed out of the source: this page is
     * owner-only, and the owner is exactly the user for whom every other reason
     * to hide a link is already false. If it stays false for them, the page is
     * simply never navigable.
     */
    public static function neverAppearsInSidebar(string $class): bool
    {
        try {
            return ! $class::shouldRegisterNavigation();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * The module a page belongs to, when that module is currently switched off.
     *
     * A disabled module closes its pages for everyone, whatever this screen
     * says — and the "code rule" tag never caught these, because the rule
     * lives in the HasModuleAccess trait rather than the page's own file. So
     * the row read as fully granted while the page 403'd, which is precisely
     * the confusion of being refused a page you were told you could see.
     */
    public static function disabledModuleFor(string $class): ?string
    {
        try {
            if (! in_array(\App\Filament\Concerns\HasModuleAccess::class, class_uses_recursive($class), true)) {
                return null;
            }

            $slug = (new \ReflectionClass($class))->getStaticPropertyValue('moduleSlug', null);

            if (! is_string($slug) || $slug === '') {
                return null;
            }

            return \App\Support\AdminModules::isEnabled($slug) ? null : $slug;
        } catch (\Throwable) {
            return null;
        }
    }

    protected static function pageAccessLabel(string $class, string $label): \Illuminate\Support\HtmlString
    {
        $tags = '';

        if ($module = static::disabledModuleFor($class)) {
            $tags .= static::tag(
                'module off',
                'The "' . $module . '" module is switched off, so this page is closed for every role '
                . 'regardless of what is ticked here. Turn the module on first.',
                'rgb(239 68 68 / .14)',
                '#b91c1c',
            );
        }

        if (static::neverAppearsInSidebar($class)) {
            $tags .= static::tag(
                'no sidebar link',
                'This page is opened from another page rather than the sidebar, so Visible '
                . 'will not add a link. Unticking it still blocks the page.',
                'rgb(107 114 128 / .15)',
                '#4b5563',
            );
        }

        if (static::hasOwnAccessRule($class)) {
            $tags .= static::tag(
                'code rule',
                'This page also enforces its own rule in code — usually admin or owner. '
                . 'Hiding it here always works; showing it may not be enough on its own.',
                'rgb(245 158 11 / .16)',
                '#b45309',
            );
        }

        return new \Illuminate\Support\HtmlString(e($label) . $tags);
    }

    private static function tag(string $text, string $title, string $background, string $color): string
    {
        return ' <span title="' . e($title) . '" style="margin-left:.375rem;padding:.0625rem .375rem;'
            . 'border-radius:9999px;background:' . $background . ';color:' . $color . ';'
            . 'font-size:.6875rem;font-weight:600;white-space:nowrap;">' . e($text) . '</span>';
    }

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
                        ->content(static::pageAccessLabel($class, $label))
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
            ->striped()
            ->emptyStateHeading('No roles')
            ->emptyStateDescription('Create a role to group permissions for your team.')
            ->emptyStateIcon('heroicon-o-shield-check')
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
