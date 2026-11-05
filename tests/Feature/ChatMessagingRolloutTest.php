<?php
namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class ChatMessagingRolloutTest extends TestCase
{
    public function test_employee_chat_rollout_is_disabled_by_default(): void
    {
        $this->assertFalse((bool) config('chatify-preview.team_enabled', false));
    }

    public function test_owner_preview_remains_separate_from_employee_chat_rollout(): void
    {
        config(['chatify-preview.team_enabled' => true, 'chatify-preview.enabled' => false]);
        $this->assertFalse((bool) config('chatify-preview.enabled'));
        $this->assertTrue((bool) config('chatify-preview.team_enabled'));
    }
}
