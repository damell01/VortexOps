<?php

namespace Tests\Feature\Reliability;

use App\Filament\Pages\SystemHealth;
use App\Jobs\TestWhatnotSignIn;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WhatnotSignInRefreshTest extends TestCase
{
    use RefreshDatabase;

    private string $cookies;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cookies = sys_get_temp_dir().'/wn-refresh-'.uniqid().'.json';
        config(['vortex.whatnot.cookies_file' => $this->cookies]);
    }

    protected function tearDown(): void
    {
        @unlink($this->cookies);
        parent::tearDown();
    }

    private function owner(): User
    {
        return User::factory()->create(['email' => config('app.owner_email') ?: 'dbellcreations@gmail.com']);
    }

    public function test_owner_pastes_an_export_and_the_sign_in_is_saved_and_tested(): void
    {
        Queue::fake();
        $this->actingAs($this->owner());
        $expires = now()->addDays(45)->timestamp;
        $export = json_encode([
            ['name' => 'usid_session', 'value' => 'abc', 'domain' => '.whatnot.com', 'expirationDate' => $expires],
            ['name' => 'cf_clearance', 'value' => 'cf', 'domain' => '.whatnot.com'],
            ['name' => 'other', 'value' => 'x', 'domain' => '.example.com'],
        ]);

        Livewire::test(SystemHealth::class)
            ->assertSee('Whatnot sign-in')->assertSee('NOT SET UP')
            ->callAction('refresh_whatnot_sign_in', ['cookies' => $export, 'test' => true])
            ->assertHasNoActionErrors()
            ->assertNotified('Whatnot sign-in saved')
            ->assertSee('Good until '.now()->addDays(45)->format('M j, Y'));

        $saved = json_decode(file_get_contents($this->cookies), true);
        $this->assertSame(['usid_session'], array_column($saved, 'name'));
        Queue::assertPushed(TestWhatnotSignIn::class);
    }

    public function test_a_bad_paste_is_rejected_and_nothing_is_written(): void
    {
        Queue::fake();
        $this->actingAs($this->owner());

        Livewire::test(SystemHealth::class)
            ->callAction('refresh_whatnot_sign_in', ['cookies' => '{"not": "cookies"}', 'test' => true])
            ->assertNotified('Sign-in not saved');

        $this->assertFileDoesNotExist($this->cookies);
        Queue::assertNothingPushed();
    }

    public function test_only_the_owner_can_replace_the_sign_in(): void
    {
        Role::findOrCreate('admin', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin);

        Livewire::test(SystemHealth::class)->assertOk()->assertActionHidden('refresh_whatnot_sign_in');
    }
}
