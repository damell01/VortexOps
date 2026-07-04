<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ShowIngestionLog;
use Illuminate\Auth\Access\HandlesAuthorization;

class ShowIngestionLogPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ShowIngestionLog');
    }

    public function view(AuthUser $authUser, ShowIngestionLog $showIngestionLog): bool
    {
        return $authUser->can('View:ShowIngestionLog');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ShowIngestionLog');
    }

    public function update(AuthUser $authUser, ShowIngestionLog $showIngestionLog): bool
    {
        return $authUser->can('Update:ShowIngestionLog');
    }

    public function delete(AuthUser $authUser, ShowIngestionLog $showIngestionLog): bool
    {
        return $authUser->can('Delete:ShowIngestionLog');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:ShowIngestionLog');
    }

    public function restore(AuthUser $authUser, ShowIngestionLog $showIngestionLog): bool
    {
        return $authUser->can('Restore:ShowIngestionLog');
    }

    public function forceDelete(AuthUser $authUser, ShowIngestionLog $showIngestionLog): bool
    {
        return $authUser->can('ForceDelete:ShowIngestionLog');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ShowIngestionLog');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ShowIngestionLog');
    }

    public function replicate(AuthUser $authUser, ShowIngestionLog $showIngestionLog): bool
    {
        return $authUser->can('Replicate:ShowIngestionLog');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ShowIngestionLog');
    }

}