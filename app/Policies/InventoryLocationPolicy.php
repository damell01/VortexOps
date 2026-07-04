<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\InventoryLocation;
use Illuminate\Auth\Access\HandlesAuthorization;

class InventoryLocationPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:InventoryLocation');
    }

    public function view(AuthUser $authUser, InventoryLocation $inventoryLocation): bool
    {
        return $authUser->can('View:InventoryLocation');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:InventoryLocation');
    }

    public function update(AuthUser $authUser, InventoryLocation $inventoryLocation): bool
    {
        return $authUser->can('Update:InventoryLocation');
    }

    public function delete(AuthUser $authUser, InventoryLocation $inventoryLocation): bool
    {
        return $authUser->can('Delete:InventoryLocation');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:InventoryLocation');
    }

    public function restore(AuthUser $authUser, InventoryLocation $inventoryLocation): bool
    {
        return $authUser->can('Restore:InventoryLocation');
    }

    public function forceDelete(AuthUser $authUser, InventoryLocation $inventoryLocation): bool
    {
        return $authUser->can('ForceDelete:InventoryLocation');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:InventoryLocation');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:InventoryLocation');
    }

    public function replicate(AuthUser $authUser, InventoryLocation $inventoryLocation): bool
    {
        return $authUser->can('Replicate:InventoryLocation');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:InventoryLocation');
    }

}