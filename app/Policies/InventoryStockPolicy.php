<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\InventoryStock;
use Illuminate\Auth\Access\HandlesAuthorization;

class InventoryStockPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:InventoryStock');
    }

    public function view(AuthUser $authUser, InventoryStock $inventoryStock): bool
    {
        return $authUser->can('View:InventoryStock');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:InventoryStock');
    }

    public function update(AuthUser $authUser, InventoryStock $inventoryStock): bool
    {
        return $authUser->can('Update:InventoryStock');
    }

    public function delete(AuthUser $authUser, InventoryStock $inventoryStock): bool
    {
        return $authUser->can('Delete:InventoryStock');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:InventoryStock');
    }

    public function restore(AuthUser $authUser, InventoryStock $inventoryStock): bool
    {
        return $authUser->can('Restore:InventoryStock');
    }

    public function forceDelete(AuthUser $authUser, InventoryStock $inventoryStock): bool
    {
        return $authUser->can('ForceDelete:InventoryStock');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:InventoryStock');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:InventoryStock');
    }

    public function replicate(AuthUser $authUser, InventoryStock $inventoryStock): bool
    {
        return $authUser->can('Replicate:InventoryStock');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:InventoryStock');
    }

}