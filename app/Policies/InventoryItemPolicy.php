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
        // Allow admins, owners, and streamers to view inventory items
        if ($authUser->isAdmin() || $authUser->isOwner()) {
            return $authUser->can('ViewAny:InventoryItem');
        }

        if ($authUser->isStreamer()) {
            return true;
        }

        return false;
    }

    public function view(AuthUser $authUser, InventoryItem $inventoryItem): bool
    {
        // Allow admins, owners, and streamers to view inventory items
        if ($authUser->isAdmin() || $authUser->isOwner()) {
            return $authUser->can('View:InventoryItem');
        }

        if ($authUser->isStreamer()) {
            return true;
        }

        return false;
    }

    public function create(AuthUser $authUser): bool
    {
        // Allow admins, owners, and streamers to create inventory items
        if ($authUser->isAdmin() || $authUser->isOwner()) {
            return $authUser->can('Create:InventoryItem');
        }

        if ($authUser->isStreamer()) {
            return true;
        }

        return false;
    }

    public function update(AuthUser $authUser, InventoryItem $inventoryItem): bool
    {
        // Allow admins and owners
        if ($authUser->isAdmin() || $authUser->isOwner()) {
            return $authUser->can('Update:InventoryItem');
        }

        // Streamers can only edit items in their assigned inventory locations
        if ($authUser->isStreamer()) {
            $streamer = $authUser->streamer;
            if (!$streamer) {
                return false;
            }

            return $inventoryItem->stock()
                ->whereIn('inventory_location_id', $streamer->inventoryLocations()->pluck('id'))
                ->exists();
        }

        return false;
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