<?php

namespace App\Filament\Pages;

use App\Support\NavLayout;
use App\Support\NavVisibility;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Spatie\Permission\Models\Role;

class NavigationManager extends Page
{
    use \App\Filament\Concerns\HasAdminNavVisibility;

    protected static ?string $title = 'Navigation Manager';
    protected static ?string $navigationLabel = 'Navigation Manager';
    protected static ?string $slug = 'navigation-manager';
    protected static string $view = 'filament.pages.navigation-manager';

    public string $tab = 'layout';
    public string $selectedRole = '';

    /** @var array<string,array{class:string,label:string,group:string,sort:int}> */
    public array $catalog = [];

    /** @var array<int,array{id:string,label:string,sort:int}> */
    public array $layoutGroups = [];

    /** @var array<string,array{group:string,sort:int,label:?string}> */
    public array $layoutItems = [];

    /** @var array<string,string> */
    public array $roleStates = [];

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Settings';
    }

    public static function getNavigationSort(): ?int
    {
        return 3;
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-bars-3-bottom-left';
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();
        return ($user?->isOwner() || $user?->isSuperAdmin()) ?? false;
    }

    public function getSubheading(): ?string
    {
        return 'Arrange the sidebar, choose what each role can use, and preview the result before saving.';
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->catalog = $this->discoverNavigation();
        $this->loadLayout();
        $this->selectedRole = $this->roleNames()[0] ?? '';
        $this->loadRoleState();
    }

    /** @return array<int,string> */
    public function roleNames(): array
    {
        try {
            return Role::query()->orderBy('name')->pluck('name')->values()->all();
        } catch (\Throwable) {
            return [];
        }
    }

    public function setTab(string $tab): void
    {
        if (in_array($tab, ['layout', 'access'], true)) {
            $this->tab = $tab;
        }
    }

    public function selectRole(string $role): void
    {
        if (! in_array($role, $this->roleNames(), true)) {
            return;
        }

        $this->selectedRole = $role;
        $this->loadRoleState();
    }

    public function setAccess(string $class, string $state): void
    {
        if (! isset($this->catalog[$class]) || ! in_array($state, ['manage', 'view', 'hidden'], true) || $this->selectedRole === '') {
            return;
        }

        $visible = NavVisibility::visibleForRole($this->selectedRole);
        $readonly = NavVisibility::readonlyForRole($this->selectedRole);
        $hidden = NavVisibility::hiddenForRole($this->selectedRole);

        $visible = array_values(array_diff($visible, [$class]));
        $readonly = array_values(array_diff($readonly, [$class]));
        $hidden = array_values(array_diff($hidden, [$class]));

        if ($state !== 'hidden') {
            $visible[] = $class;
        }
        if ($state === 'view') {
            $readonly[] = $class;
        }
        if ($state === 'hidden') {
            $hidden[] = $class;
        }

        NavVisibility::setVisibleForRole($this->selectedRole, array_values(array_unique($visible)));
        NavVisibility::setReadonlyForRole($this->selectedRole, array_values(array_unique($readonly)));
        NavVisibility::setHiddenForRole($this->selectedRole, array_values(array_unique($hidden)));
        NavVisibility::flushMemo();
        \App\Support\RoleAccess::flushMemo();

        $this->roleStates[$class] = $state;
    }

    public function resetRoleAccess(): void
    {
        if ($this->selectedRole === '') {
            return;
        }

        // Empty explicit maps return the role to the app's built-in fallback rules.
        $visibleMap = NavVisibility::visibleByRole();
        unset($visibleMap[$this->selectedRole]);
        \App\Models\Setting::set('role_visible_nav', json_encode($visibleMap));

        $hiddenMap = NavVisibility::hiddenByRole();
        unset($hiddenMap[$this->selectedRole]);
        \App\Models\Setting::set('role_hidden_nav', json_encode($hiddenMap));

        $readonlyMap = NavVisibility::readonlyByRole();
        unset($readonlyMap[$this->selectedRole]);
        \App\Models\Setting::set('role_readonly_nav', json_encode($readonlyMap));

        NavVisibility::flushMemo();
        \App\Support\RoleAccess::flushMemo();
        $this->loadRoleState();

        Notification::make()->title('Role access reset')->body('This role is using the app’s built-in access rules again.')->success()->send();
    }

    public function moveItem(string $class, string $group, int $index): void
    {
        if (! isset($this->catalog[$class])) {
            return;
        }

        $groupLabels = collect($this->layoutGroups)->pluck('label')->all();
        if (! in_array($group, $groupLabels, true)) {
            return;
        }

        $siblings = collect($this->layoutItems)
            ->filter(fn ($item, $key) => $key !== $class && ($item['group'] ?? '') === $group)
            ->sortBy('sort')
            ->keys()
            ->values()
            ->all();

        $index = max(0, min($index, count($siblings)));
        array_splice($siblings, $index, 0, [$class]);

        foreach ($siblings as $i => $siblingClass) {
            $this->layoutItems[$siblingClass]['group'] = $group;
            $this->layoutItems[$siblingClass]['sort'] = ($i + 1) * 10;
        }

        // Remove it from its old group by normalizing that group's sort too.
        foreach ($this->layoutGroups as $g) {
            $label = $g['label'];
            $classes = collect($this->layoutItems)
                ->filter(fn ($item) => ($item['group'] ?? '') === $label)
                ->sortBy('sort')->keys()->values()->all();
            foreach ($classes as $i => $c) {
                $this->layoutItems[$c]['sort'] = ($i + 1) * 10;
            }
        }
    }

    public function moveGroup(string $groupId, int $index): void
    {
        $groups = array_values($this->layoutGroups);
        $from = collect($groups)->search(fn ($g) => ($g['id'] ?? '') === $groupId);
        if ($from === false) {
            return;
        }

        $moving = $groups[$from];
        array_splice($groups, $from, 1);
        $index = max(0, min($index, count($groups)));
        array_splice($groups, $index, 0, [$moving]);

        foreach ($groups as $i => &$group) {
            $group['sort'] = ($i + 1) * 10;
        }
        $this->layoutGroups = $groups;
    }

    public function renameGroup(string $groupId, string $label): void
    {
        $label = trim($label);
        if ($label === '') {
            return;
        }

        foreach ($this->layoutGroups as &$group) {
            if (($group['id'] ?? '') !== $groupId) {
                continue;
            }
            $old = $group['label'];
            $group['label'] = $label;
            foreach ($this->layoutItems as &$item) {
                if (($item['group'] ?? '') === $old) {
                    $item['group'] = $label;
                }
            }
            break;
        }
    }

    public function renameItem(string $class, string $label): void
    {
        if (! isset($this->layoutItems[$class])) {
            return;
        }
        $this->layoutItems[$class]['label'] = trim($label) ?: null;
    }

    public function saveLayout(): void
    {
        NavLayout::set($this->layoutGroups, $this->layoutItems);
        Notification::make()->title('Navigation layout saved')->body('The sidebar will use the new module and item order on the next navigation render.')->success()->send();
    }

    public function resetLayout(): void
    {
        NavLayout::set([], []);
        $this->catalog = $this->discoverNavigation();
        $this->loadLayout();
        Notification::make()->title('Navigation layout reset')->success()->send();
    }

    /** @return array<string,array{class:string,label:string,group:string,sort:int}> */
    private function discoverNavigation(): array
    {
        $panel = Filament::getCurrentPanel();
        $classes = array_values(array_unique(array_merge($panel?->getPages() ?? [], $panel?->getResources() ?? [])));
        $out = [];

        foreach ($classes as $class) {
            if (! is_string($class) || ! class_exists($class) || $class === static::class || str_contains($class, '\\Auth\\')) {
                continue;
            }

            try {
                $label = method_exists($class, 'getNavigationLabel') ? $class::getNavigationLabel() : class_basename($class);
                $group = method_exists($class, 'getNavigationGroup') ? $class::getNavigationGroup() : null;
                $sort = method_exists($class, 'getNavigationSort') ? ($class::getNavigationSort() ?? 0) : 0;
            } catch (\Throwable) {
                continue;
            }

            if ($group instanceof \BackedEnum) {
                $group = $group->value;
            } elseif ($group instanceof \UnitEnum) {
                $group = $group->name;
            }

            $group = is_string($group) && trim($group) !== '' ? trim($group) : 'General';
            $label = is_string($label) && trim($label) !== '' ? trim($label) : class_basename($class);

            // Account/security utility pages are not part of the business sidebar builder.
            if (in_array($class, [EditProfile::class, TwoFactorAuth::class, TwoFactorVerify::class], true)) {
                continue;
            }

            $out[$class] = ['class' => $class, 'label' => $label, 'group' => $group, 'sort' => (int) $sort];
        }

        uasort($out, fn ($a, $b) => [$a['group'], $a['sort'], $a['label']] <=> [$b['group'], $b['sort'], $b['label']]);
        return $out;
    }

    private function loadLayout(): void
    {
        $saved = NavLayout::config();
        $groups = $saved['groups'];
        $items = $saved['items'];

        $knownGroups = collect($groups)->pluck('label')->filter()->values()->all();
        foreach ($this->catalog as $class => $entry) {
            $override = $items[$class] ?? null;
            $group = is_array($override) ? ($override['group'] ?? $entry['group']) : $entry['group'];
            if (! in_array($group, $knownGroups, true)) {
                $groups[] = ['id' => (string) str($group)->slug(), 'label' => $group, 'sort' => (count($groups) + 1) * 10];
                $knownGroups[] = $group;
            }
            $items[$class] = [
                'group' => $group,
                'sort'  => (int) (is_array($override) ? ($override['sort'] ?? $entry['sort']) : $entry['sort']),
                'label' => is_array($override) ? ($override['label'] ?? null) : null,
            ];
        }

        usort($groups, fn ($a, $b) => ($a['sort'] ?? 0) <=> ($b['sort'] ?? 0));
        $this->layoutGroups = array_values($groups);
        $this->layoutItems = $items;
    }

    private function loadRoleState(): void
    {
        $this->roleStates = [];
        if ($this->selectedRole === '') {
            return;
        }

        $hasExplicit = NavVisibility::hasExplicitVisibility($this->selectedRole);
        $visible = NavVisibility::visibleForRole($this->selectedRole);
        $readonly = NavVisibility::readonlyForRole($this->selectedRole);
        $hidden = NavVisibility::hiddenForRole($this->selectedRole);

        foreach ($this->catalog as $class => $entry) {
            if ($hasExplicit) {
                $state = in_array($class, $visible, true)
                    ? (in_array($class, $readonly, true) ? 'view' : 'manage')
                    : 'hidden';
            } else {
                // Before a role has an explicit allow-list, display its legacy configuration without changing it.
                $state = in_array($class, $hidden, true)
                    ? 'hidden'
                    : (in_array($class, $readonly, true) ? 'view' : 'manage');
            }
            $this->roleStates[$class] = $state;
        }
    }

    /** @return array<string,array<int,array{class:string,label:string,state:string}>> */
    public function getPreviewGroupsProperty(): array
    {
        $groups = [];
        foreach (collect($this->layoutGroups)->sortBy('sort') as $group) {
            $label = $group['label'];
            $items = collect($this->layoutItems)
                ->filter(fn ($item, $class) => isset($this->catalog[$class]) && ($item['group'] ?? '') === $label)
                ->sortBy('sort');

            foreach ($items as $class => $item) {
                $state = $this->roleStates[$class] ?? 'manage';
                if ($state === 'hidden') {
                    continue;
                }
                $groups[$label][] = [
                    'class' => $class,
                    'label' => $item['label'] ?: $this->catalog[$class]['label'],
                    'state' => $state,
                ];
            }
        }
        return $groups;
    }
}
