<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\StreamerLoan;
use Illuminate\Auth\Access\HandlesAuthorization;

class StreamerLoanPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:StreamerLoan');
    }

    public function view(AuthUser $authUser, StreamerLoan $streamerLoan): bool
    {
        return $authUser->can('View:StreamerLoan');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:StreamerLoan');
    }

    public function update(AuthUser $authUser, StreamerLoan $streamerLoan): bool
    {
        return $authUser->can('Update:StreamerLoan');
    }

    public function delete(AuthUser $authUser, StreamerLoan $streamerLoan): bool
    {
        return $authUser->can('Delete:StreamerLoan');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:StreamerLoan');
    }

    public function restore(AuthUser $authUser, StreamerLoan $streamerLoan): bool
    {
        return $authUser->can('Restore:StreamerLoan');
    }

    public function forceDelete(AuthUser $authUser, StreamerLoan $streamerLoan): bool
    {
        return $authUser->can('ForceDelete:StreamerLoan');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:StreamerLoan');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:StreamerLoan');
    }

    public function replicate(AuthUser $authUser, StreamerLoan $streamerLoan): bool
    {
        return $authUser->can('Replicate:StreamerLoan');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:StreamerLoan');
    }

}