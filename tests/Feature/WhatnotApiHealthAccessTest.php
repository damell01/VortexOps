<?php

namespace Tests\Feature;

use App\Models\User;
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

    public function test_non_owner_cannot_save_tokens(): void
    {
        $user = User::factory()->create(['email' => 'not-owner-whatnot@example.test']);
        $this->actingAs($user)->postJson('/admin/whatnot-api/channels/1/credentials', ['token' => 'dummy'])->assertForbidden();
    }
}
