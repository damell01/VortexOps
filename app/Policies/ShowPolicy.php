<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Show;
use Illuminate\Auth\Access\HandlesAuthorization;

class ShowPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Show');
    }

    public function view(AuthUser $authUser, Show $show): bool
    {
        return $authUser->can('View:Show');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Show');
    }

    public function update(AuthUser $authUser, Show $show): bool
    {
        return $authUser->can('Update:Show');
    }

    public function delete(AuthUser $authUser, Show $show): bool
    {
        return $authUser->can('Delete:Show');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Show');
    }

    public function restore(AuthUser $authUser, Show $show): bool
    {
        return $authUser->can('Restore:Show');
    }

    public function forceDelete(AuthUser $authUser, Show $show): bool
    {
        return $authUser->can('ForceDelete:Show');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Show');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Show');
    }

    public function replicate(AuthUser $authUser, Show $show): bool
    {
        return $authUser->can('Replicate:Show');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Show');
    }

}