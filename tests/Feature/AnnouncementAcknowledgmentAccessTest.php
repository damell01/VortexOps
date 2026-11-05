<?php
namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class AnnouncementAcknowledgmentAccessTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;
    protected function setUp(): void
    {
        parent::setUp();
        config(['chatify-preview.available' => true]);
    }

    public function test_guest_cannot_acknowledge_announcement(): void
    {
        $this->postJson('/admin/communications/announcements/1/acknowledge')->assertUnauthorized();
    }

    public function test_regular_employee_cannot_access_owner_preview_acknowledgments(): void
    {
        $user = User::factory()->create();
        $announcement = \App\Models\TeamAnnouncement::create(['title' => 'Test', 'body' => 'Test', 'tone' => 'info', 'audience' => 'all', 'is_preview' => true, 'status' => 'draft', 'created_by' => $user->id, 'starts_at' => now(), 'ends_at' => now()->addDay()]);
        $this->actingAs($user)
            ->postJson('/admin/communications/announcements/'.$announcement->id.'/acknowledge')
            ->assertForbidden();
    }
}
