<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class ChatGroupRolesAccessTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;
    protected function setUp(): void
    {
        parent::setUp();
        config(['chatify-preview.available' => true]);
    }

    public function test_guest_cannot_list_role_group_recipients(): void
    {
        $this->getJson('/admin/communications/group-roles')->assertUnauthorized();
    }

    public function test_regular_employee_cannot_list_role_group_recipients(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/admin/communications/group-roles')
            ->assertForbidden();
    }
}
