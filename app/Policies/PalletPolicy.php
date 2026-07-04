<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Pallet;
use Illuminate\Auth\Access\HandlesAuthorization;

class PalletPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Pallet');
    }

    public function view(AuthUser $authUser, Pallet $pallet): bool
    {
        return $authUser->can('View:Pallet');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Pallet');
    }

    public function update(AuthUser $authUser, Pallet $pallet): bool
    {
        return $authUser->can('Update:Pallet');
    }

    public function delete(AuthUser $authUser, Pallet $pallet): bool
    {
        return $authUser->can('Delete:Pallet');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Pallet');
    }

    public function restore(AuthUser $authUser, Pallet $pallet): bool
    {
        return $authUser->can('Restore:Pallet');
    }

    public function forceDelete(AuthUser $authUser, Pallet $pallet): bool
    {
        return $authUser->can('ForceDelete:Pallet');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Pallet');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Pallet');
    }

    public function replicate(AuthUser $authUser, Pallet $pallet): bool
    {
        return $authUser->can('Replicate:Pallet');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Pallet');
    }

}