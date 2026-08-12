<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\HowItWorks;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HowItWorksTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.owner_email' => 'owner@vortex.com']);
    }

    public function test_any_authenticated_user_can_access(): void
    {
        $this->actingAs(User::factory()->create());
        $this->assertTrue(HowItWorks::canAccess());
    }

    public function test_role_guide_matches_the_viewers_role(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'streamer', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'fulfillment', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'fulfillment_admin', 'guard_name' => 'web']);

        $owner = User::factory()->create(['email' => 'owner@vortex.com']);
        $this->actingAs($owner);
        Livewire::test(HowItWorks::class)->assertSee('Owner');

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin);
        Livewire::test(HowItWorks::class)->assertSee('You\'re: Admin', false);

        $streamer = User::factory()->create();
        $streamer->assignRole('streamer');
        $this->actingAs($streamer);
        Livewire::test(HowItWorks::class)->assertSee('Streamer Statement');

        $fulfillment = User::factory()->create();
        $fulfillment->assignRole('fulfillment');
        $this->actingAs($fulfillment);
        Livewire::test(HowItWorks::class)->assertSee('You\'re: Fulfillment', false);

        $fulfillmentAdmin = User::factory()->create();
        $fulfillmentAdmin->assignRole('fulfillment_admin');
        $this->actingAs($fulfillmentAdmin);
        Livewire::test(HowItWorks::class)->assertSee('Every channel\'s fulfillment work');
    }
}
