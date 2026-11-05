<?php

namespace Tests\Unit;

use App\Http\Requests\AdminChatGroupRequest;
use App\Models\User;
use PHPUnit\Framework\TestCase;

class AdminChatGroupRequestTest extends TestCase
{
    public function test_admin_only_group_request_extends_chatify_validation(): void
    {
        $this->assertTrue(is_subclass_of(AdminChatGroupRequest::class, \Chatify\Http\Requests\CreateGroupConversationRequest::class));
    }
}
