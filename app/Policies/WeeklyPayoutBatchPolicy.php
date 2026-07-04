<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\WeeklyPayoutBatch;
use Illuminate\Auth\Access\HandlesAuthorization;

class WeeklyPayoutBatchPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:WeeklyPayoutBatch');
    }

    public function view(AuthUser $authUser, WeeklyPayoutBatch $weeklyPayoutBatch): bool
    {
        return $authUser->can('View:WeeklyPayoutBatch');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:WeeklyPayoutBatch');
    }

    public function update(AuthUser $authUser, WeeklyPayoutBatch $weeklyPayoutBatch): bool
    {
        return $authUser->can('Update:WeeklyPayoutBatch');
    }

    public function delete(AuthUser $authUser, WeeklyPayoutBatch $weeklyPayoutBatch): bool
    {
        return $authUser->can('Delete:WeeklyPayoutBatch');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:WeeklyPayoutBatch');
    }

    public function restore(AuthUser $authUser, WeeklyPayoutBatch $weeklyPayoutBatch): bool
    {
        return $authUser->can('Restore:WeeklyPayoutBatch');
    }

    public function forceDelete(AuthUser $authUser, WeeklyPayoutBatch $weeklyPayoutBatch): bool
    {
        return $authUser->can('ForceDelete:WeeklyPayoutBatch');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:WeeklyPayoutBatch');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:WeeklyPayoutBatch');
    }

    public function replicate(AuthUser $authUser, WeeklyPayoutBatch $weeklyPayoutBatch): bool
    {
        return $authUser->can('Replicate:WeeklyPayoutBatch');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:WeeklyPayoutBatch');
    }

}