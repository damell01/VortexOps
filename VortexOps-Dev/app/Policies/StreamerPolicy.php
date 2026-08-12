<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Streamer;
use Illuminate\Auth\Access\HandlesAuthorization;

class StreamerPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Streamer');
    }

    public function view(AuthUser $authUser, Streamer $streamer): bool
    {
        return $authUser->can('View:Streamer');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Streamer');
    }

    public function update(AuthUser $authUser, Streamer $streamer): bool
    {
        return $authUser->can('Update:Streamer');
    }

    public function delete(AuthUser $authUser, Streamer $streamer): bool
    {
        return $authUser->can('Delete:Streamer');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Streamer');
    }

    public function restore(AuthUser $authUser, Streamer $streamer): bool
    {
        return $authUser->can('Restore:Streamer');
    }

    public function forceDelete(AuthUser $authUser, Streamer $streamer): bool
    {
        return $authUser->can('ForceDelete:Streamer');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Streamer');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Streamer');
    }

    public function replicate(AuthUser $authUser, Streamer $streamer): bool
    {
        return $authUser->can('Replicate:Streamer');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Streamer');
    }

}