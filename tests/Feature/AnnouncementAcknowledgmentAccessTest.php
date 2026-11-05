<?php
namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class AnnouncementAcknowledgmentAccessTest extends TestCase
{
    public function test_guest_cannot_acknowledge_announcement(): void
    {
        $this->postJson('/admin/communications/announcements/1/acknowledge')->assertUnauthorized();
    }

    public function test_regular_employee_cannot_access_owner_preview_acknowledgments(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)
            ->postJson('/admin/communications/announcements/1/acknowledge')
            ->assertForbidden();
    }
}
