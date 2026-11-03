<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WhatnotChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatnotApiHealthAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_health(): void
    {
        $this->get('/admin/whatnot-api/health')->assertRedirect();
    }

    public function test_non_owner_cannot_access_health(): void
    {
        $user = User::factory()->create(['email' => 'not-owner-whatnot@example.test']);
        $this->actingAs($user)->get('/admin/whatnot-api/health')->assertForbidden();
    }

    /** A real channel, so the owner check is what answers rather than a missing record. */
    private function channelId(): int
    {
        return WhatnotChannel::create(['name' => 'Test Seller', 'whatnot_username' => 'test-seller'])->id;
    }

    public function test_non_owner_cannot_save_tokens(): void
    {
        $user = User::factory()->create(['email' => 'not-owner-whatnot@example.test']);
        $this->actingAs($user)->postJson('/admin/whatnot-api/channels/'.$this->channelId().'/credentials', ['token' => 'dummy'])->assertForbidden();
    }
    public function test_non_owner_cannot_activate_api_capture(): void
    {
        $user = User::factory()->create(['email' => 'non-owner-api-toggle@example.test']);
        $this->actingAs($user)->postJson('/admin/whatnot-api/channels/'.$this->channelId().'/toggle', [
            'enabled' => true, 'confirmation' => 'ENABLE READ ONLY',
        ])->assertForbidden();
    }

    public function test_owner_tools_api_page_is_owner_only_and_defaults_to_disabled(): void
    {
        $user = User::factory()->create(['email' => 'non-owner-control-center@example.test']);
        $this->actingAs($user)->get('/admin/whatnot-api/health')->assertForbidden();
        $this->assertFalse((bool) config('services.whatnot_seller.live_requests_enabled'));
        $this->assertFalse((bool) config('services.whatnot_seller.sync_scheduler_enabled'));
    }

    public function test_testing_before_a_token_is_saved_says_so_instead_of_404(): void
    {
        $owner = User::factory()->create(['email' => config('app.owner_email')]);
        $id = $this->channelId();

        $this->actingAs($owner)->postJson('/admin/whatnot-api/channels/'.$id.'/test')
            ->assertStatus(422)->assertJsonPath('message', 'No API token is saved for this channel yet. Paste one and press Save token first.');

        $this->actingAs($owner)->postJson('/admin/whatnot-api/channels/'.$id.'/credentials', ['token' => 'fixture-token'])
            ->assertOk()->assertJsonPath('configured', true);
    }
}
