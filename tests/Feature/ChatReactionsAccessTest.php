<?php
namespace Tests\Feature;
use App\Models\User;
use Tests\TestCase;

class ChatReactionsAccessTest extends TestCase
{
    public function test_employee_cannot_access_reactions_before_team_rollout(): void
    {
        config([
            'chatify-preview.enabled' => true,
            'chatify-preview.team_enabled' => false,
        ]);
        $this->actingAs(User::factory()->create())
            ->getJson('/admin/communications/reactions/batch?ids[]=example')
            ->assertForbidden();
    }

    public function test_disabled_preview_rejects_reaction_mutations(): void
    {
        config(['chatify-preview.enabled' => false]);
        $this->actingAs(User::factory()->create())
            ->postJson('/admin/communications/reactions/example', ['emoji' => '👍'])
            ->assertNotFound();
    }

    public function test_disabled_preview_rejects_reactions(): void
    {
        config(['chatify-preview.enabled' => false]);
        $this->actingAs(User::factory()->create())
            ->getJson('/admin/communications/reactions/example')
            ->assertNotFound();
    }
}
