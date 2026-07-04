<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\DeductionRequest;
use Illuminate\Auth\Access\HandlesAuthorization;

class DeductionRequestPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:DeductionRequest');
    }

    public function view(AuthUser $authUser, DeductionRequest $deductionRequest): bool
    {
        return $authUser->can('View:DeductionRequest');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:DeductionRequest');
    }

    public function update(AuthUser $authUser, DeductionRequest $deductionRequest): bool
    {
        return $authUser->can('Update:DeductionRequest');
    }

    public function delete(AuthUser $authUser, DeductionRequest $deductionRequest): bool
    {
        return $authUser->can('Delete:DeductionRequest');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:DeductionRequest');
    }

    public function restore(AuthUser $authUser, DeductionRequest $deductionRequest): bool
    {
        return $authUser->can('Restore:DeductionRequest');
    }

    public function forceDelete(AuthUser $authUser, DeductionRequest $deductionRequest): bool
    {
        return $authUser->can('ForceDelete:DeductionRequest');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:DeductionRequest');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:DeductionRequest');
    }

    public function replicate(AuthUser $authUser, DeductionRequest $deductionRequest): bool
    {
        return $authUser->can('Replicate:DeductionRequest');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:DeductionRequest');
    }

}