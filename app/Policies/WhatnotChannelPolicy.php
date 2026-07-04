<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\WhatnotChannel;
use Illuminate\Auth\Access\HandlesAuthorization;

class WhatnotChannelPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:WhatnotChannel');
    }

    public function view(AuthUser $authUser, WhatnotChannel $whatnotChannel): bool
    {
        return $authUser->can('View:WhatnotChannel');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:WhatnotChannel');
    }

    public function update(AuthUser $authUser, WhatnotChannel $whatnotChannel): bool
    {
        return $authUser->can('Update:WhatnotChannel');
    }

    public function delete(AuthUser $authUser, WhatnotChannel $whatnotChannel): bool
    {
        return $authUser->can('Delete:WhatnotChannel');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:WhatnotChannel');
    }

    public function restore(AuthUser $authUser, WhatnotChannel $whatnotChannel): bool
    {
        return $authUser->can('Restore:WhatnotChannel');
    }

    public function forceDelete(AuthUser $authUser, WhatnotChannel $whatnotChannel): bool
    {
        return $authUser->can('ForceDelete:WhatnotChannel');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:WhatnotChannel');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:WhatnotChannel');
    }

    public function replicate(AuthUser $authUser, WhatnotChannel $whatnotChannel): bool
    {
        return $authUser->can('Replicate:WhatnotChannel');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:WhatnotChannel');
    }

}