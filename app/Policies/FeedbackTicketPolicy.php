<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\FeedbackTicket;
use Illuminate\Auth\Access\HandlesAuthorization;

class FeedbackTicketPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:FeedbackTicket');
    }

    public function view(AuthUser $authUser, FeedbackTicket $feedbackTicket): bool
    {
        return $authUser->can('View:FeedbackTicket');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:FeedbackTicket');
    }

    public function update(AuthUser $authUser, FeedbackTicket $feedbackTicket): bool
    {
        return $authUser->can('Update:FeedbackTicket');
    }

    public function delete(AuthUser $authUser, FeedbackTicket $feedbackTicket): bool
    {
        return $authUser->can('Delete:FeedbackTicket');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:FeedbackTicket');
    }

    public function restore(AuthUser $authUser, FeedbackTicket $feedbackTicket): bool
    {
        return $authUser->can('Restore:FeedbackTicket');
    }

    public function forceDelete(AuthUser $authUser, FeedbackTicket $feedbackTicket): bool
    {
        return $authUser->can('ForceDelete:FeedbackTicket');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:FeedbackTicket');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:FeedbackTicket');
    }

    public function replicate(AuthUser $authUser, FeedbackTicket $feedbackTicket): bool
    {
        return $authUser->can('Replicate:FeedbackTicket');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:FeedbackTicket');
    }

}