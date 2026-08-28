<?php

namespace App\Filament\Resources\RoleResource\Pages;

use App\Filament\Resources\RoleResource;
use App\Support\NavVisibility;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class ListRoles extends ListRecords
{
    protected static string $resource = RoleResource::class;

    protected string $view = 'filament.resources.role-resource.pages.list-roles';

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('New Role')];
    }

    public function getSubheading(): ?string
    {
        return 'Choose a role to see its effective page access at a glance, then open it to make changes. Detailed Spatie permissions remain available inside each role.';
    }

    public function roleCards(): array
    {
        $total = count(RoleResource::roleControlledPages());

        return Role::query()->withCount('users')->orderBy('name')->get()->map(function (Role $role) use ($total): array {
            $configured = NavVisibility::hasExplicitVisibility($role->name);
            $visible = $configured
                ? NavVisibility::visibleForRole($role->name)
                : array_values(array_diff(RoleResource::roleControlledPages(), NavVisibility::hiddenForRole($role->name)));
            $readonly = NavVisibility::readonlyForRole($role->name);
            $view = count(array_intersect($visible, $readonly));
            $manage = max(0, count($visible) - $view);

            return [
                'label' => Str::headline($role->name),
                'core' => RoleResource::isCoreRole($role->name),
                'manage' => $manage,
                'view' => $view,
                'hidden' => max(0, $total - count($visible)),
                'users' => $role->users_count,
                'url' => RoleResource::getUrl('edit', ['record' => $role]),
            ];
        })->all();
    }
}
