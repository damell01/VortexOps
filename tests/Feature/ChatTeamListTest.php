<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** The Messages "new conversation" picker lists teammates by name and role, and only for people who can message. */
class ChatTeamListTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_owner_gets_the_team_and_others_are_refused_while_messaging_is_owner_only(): void
    {
        config(['chatify-preview.available' => true, 'chatify-preview.team_enabled' => false]);
        Role::findOrCreate('fulfillment_admin', 'web');
        $owner = User::factory()->create(['email' => 'dbellcreations@gmail.com']);
        $crystal = User::factory()->create(['name' => 'Crystal Rice']);
        $crystal->assignRole('fulfillment_admin');

        $this->actingAs($owner)->getJson('/admin/communications/team')->assertOk()
            ->assertJsonPath('data.0.attributes.name', 'Crystal Rice')
            ->assertJsonPath('data.0.attributes.role', 'Fulfillment Admin')
            ->assertJsonMissing(['name' => $owner->name]);
        $this->actingAs($crystal)->getJson('/admin/communications/team')->assertForbidden();

        config(['chatify-preview.team_enabled' => true]);
        $this->actingAs($crystal)->getJson('/admin/communications/team')->assertOk();

        // Messaging switched off: the route is hidden altogether.
        config(['chatify-preview.available' => false]);
        $this->actingAs($owner)->getJson('/admin/communications/team')->assertNotFound();
    }
}
