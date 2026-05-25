<?php

namespace App\Modules\ProjectHub\Support;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ProjectHub
{
    public static function visibleProjectsFor(?User $user): Builder
    {
        $query = Project::query();

        if (! $user) {
            return $query->where('client_visible', true);
        }

        if ($user->isSuperAdmin() || $user->isAdmin()) {
            return $query;
        }

        return $query->where('client_visible', true);
    }

    public static function defaultProjectId(?User $user): ?int
    {
        return static::visibleProjectsFor($user)
            ->where('is_active', true)
            ->orderByDesc('updated_at')
            ->value('id')
            ?? static::visibleProjectsFor($user)->orderByDesc('updated_at')->value('id');
    }

    public static function portalTitle(): string
    {
        return 'Project Hub';
    }
}
