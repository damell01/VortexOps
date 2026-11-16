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

    public function test_search_finds_teammates_by_name_or_role_and_supports_role_filters(): void
    {
        config(['chatify-preview.available' => true, 'chatify-preview.team_enabled' => false]);
        Role::findOrCreate('streamer', 'web');
        Role::findOrCreate('fulfillment_admin', 'web');
        $owner = User::factory()->create(['email' => config('app.owner_email')]);
        $streamer = User::factory()->create(['name' => 'Zelda Watkins']);
        $streamer->assignRole('streamer');
        $fulfillment = User::factory()->create(['name' => 'Crystal Rice']);
        $fulfillment->assignRole('fulfillment_admin');

        $this->actingAs($owner)->getJson('/admin/communications/team?search=Zelda')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.attributes.name', 'Zelda Watkins');
        $this->getJson('/admin/communications/team?search=streamer')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', (string) $streamer->id);
        $this->getJson('/admin/communications/team?role=fulfillment')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', (string) $fulfillment->id);
        $this->getJson('/admin/communications/team?role=invalid')->assertStatus(422);
    }

    public function test_production_pilot_picker_lists_only_owner_and_admin_accounts(): void
    {
        config([
            'chatify-preview.available' => true,
            'chatify-preview.privileged_preview_enabled' => true,
            'chatify-preview.enabled' => true,
            'chatify-preview.team_enabled' => false,
        ]);
        foreach (['admin', 'super_admin', 'fulfillment_admin', 'streamer'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        $owner = User::factory()->create(['email' => config('app.owner_email') ?: 'dbellcreations@gmail.com']);
        $admin = User::factory()->create(['name' => 'Admin Pilot']);
        $admin->assignRole('admin');
        $superAdmin = User::factory()->create(['name' => 'Super Admin Pilot']);
        $superAdmin->assignRole('super_admin');
        $fulfillment = User::factory()->create(['name' => 'Fulfillment Manager']);
        $fulfillment->assignRole('fulfillment_admin');
        $streamer = User::factory()->create(['name' => 'Streamer Pilot']);
        $streamer->assignRole('streamer');
        User::factory()->create(['name' => 'No Role']);

        $this->actingAs($owner)->getJson('/admin/communications/team')
            ->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.attributes.name', 'Admin Pilot')
            ->assertJsonPath('data.1.attributes.name', 'Super Admin Pilot');

        $this->actingAs($admin)->getJson('/admin/communications/team')
            ->assertOk()->assertJsonCount(2, 'data');
        $this->actingAs($fulfillment)->getJson('/admin/communications/team')->assertForbidden();
        $this->actingAs($streamer)->getJson('/admin/communications/team')->assertForbidden();
    }

    public function test_picker_returns_an_indication_when_more_than_sixty_teammates_exist(): void
    {
        config(['chatify-preview.available' => true]);
        $owner = User::factory()->create(['email' => config('app.owner_email')]);
        User::factory()->count(62)->create();

        $this->actingAs($owner)->getJson('/admin/communications/team')
            ->assertOk()->assertJsonCount(60, 'data')->assertJsonPath('has_more', true);
    }
}
