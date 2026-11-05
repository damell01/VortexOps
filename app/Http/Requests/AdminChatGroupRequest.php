<?php

namespace App\Http\Requests;

use Chatify\Http\Requests\CreateGroupConversationRequest;

/**
 * Restrict group creation at the API boundary, not merely in the messenger UI.
 * Direct conversations retain Chatify's existing authorization.
 */
class AdminChatGroupRequest extends CreateGroupConversationRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return parent::authorize()
            && $user !== null
            && ($user->isAdmin() || $user->isOwner());
    }
}
