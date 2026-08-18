<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The owner is a single super-user identified by email, and several pages are
 * gated on nothing else. That check reads a config value which ships blank —
 * and a blank env var is an empty string, not a missing key, so config()'s
 * default argument never fired and every email was compared against ''.
 *
 * The failure is silent: no error, the nav links simply never appear and the
 * pages 403. These assert the check resolves and that the pages behind it are
 * reachable by the owner and nobody else.
 */
class OwnerAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The address the app falls back to, stated here rather than read from
     * config. Building the owner out of config() makes these tests
     * self-fulfilling: with the config empty the check compares '' to '' and
     * passes while the feature is entirely broken.
     */
    private const OWNER = 'dbellcreations@gmail.com';

    private function userWithEmail(string $email): User
    {
        $user = User::factory()->create(['email' => $email]);

        return $user->fresh();
    }

    public function test_the_owner_email_resolves_to_a_real_address(): void
    {
        $this->assertNotSame('', (string) config('app.owner_email'));
        $this->assertSame(self::OWNER, config('app.owner_email'));
    }

    public function test_the_configured_owner_is_recognised(): void
    {
        $this->assertTrue($this->userWithEmail(self::OWNER)->isOwner());
    }

    public function test_anyone_else_is_not_the_owner(): void
    {
        $this->assertFalse($this->userWithEmail('someone@else.test')->isOwner());
    }

    public function test_a_blank_configured_owner_still_falls_back(): void
    {
        // Reproduces the shipped .env: the key is present but empty.
        config(['app.owner_email' => '']);

        $this->assertTrue($this->userWithEmail(self::OWNER)->isOwner());
    }

    public static function ownerOnlyPages(): array
    {
        return [
            'roles'            => [\App\Filament\Resources\RoleResource\Pages\ListRoles::class],
            'whatnot backfill' => [\App\Filament\Pages\WhatnotBackfill::class],
        ];
    }

    #[DataProvider('ownerOnlyPages')]
    public function test_owner_only_pages_are_reachable_by_the_owner(string $page): void
    {
        $this->actingAs($this->userWithEmail(self::OWNER));

        Livewire::test($page)->assertOk();
    }

    #[DataProvider('ownerOnlyPages')]
    public function test_owner_only_pages_stay_shut_to_everyone_else(string $page): void
    {
        $this->actingAs($this->userWithEmail('not-the-owner@example.test'));

        Livewire::test($page)->assertForbidden();
    }
}
