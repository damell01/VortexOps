<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\InventoryItem;
use Illuminate\Auth\Access\HandlesAuthorization;

class InventoryItemPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:InventoryItem');
    }

    public function view(AuthUser $authUser, InventoryItem $inventoryItem): bool
    {
        return $authUser->can('View:InventoryItem');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:InventoryItem');
    }

    public function update(AuthUser $authUser, InventoryItem $inventoryItem): bool
    {
        return $authUser->can('Update:InventoryItem');
    }

    public function delete(AuthUser $authUser, InventoryItem $inventoryItem): bool
    {
        return $authUser->can('Delete:InventoryItem');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:InventoryItem');
    }

    public function restore(AuthUser $authUser, InventoryItem $inventoryItem): bool
    {
        return $authUser->can('Restore:InventoryItem');
    }

    public function forceDelete(AuthUser $authUser, InventoryItem $inventoryItem): bool
    {
        return $authUser->can('ForceDelete:InventoryItem');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:InventoryItem');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:InventoryItem');
    }

    public function replicate(AuthUser $authUser, InventoryItem $inventoryItem): bool
    {
        return $authUser->can('Replicate:InventoryItem');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:InventoryItem');
    }

}