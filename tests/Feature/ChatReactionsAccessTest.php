<?php
namespace Tests\Feature;
use App\Models\User;
use Tests\TestCase;

class ChatReactionsAccessTest extends TestCase
{
    public function test_disabled_preview_rejects_reactions(): void
    {
        config(['chatify-preview.enabled' => false]);
        $this->actingAs(User::factory()->create())
            ->getJson('/admin/communications/reactions/example')
            ->assertNotFound();
    }
}
