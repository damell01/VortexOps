<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\AppSettings;
use App\Models\Setting;
use App\Models\Show;
use App\Models\User;
use App\Models\WhatnotChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ClearDemoDataActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.owner_email' => 'owner@vortex.com']);
    }

    private function owner(): User
    {
        return User::factory()->create(['email' => 'owner@vortex.com']);
    }

    public function test_wrong_confirmation_text_does_not_delete_data(): void
    {
        $channel = WhatnotChannel::create(['name' => 'Breaks', 'status' => 'active']);
        Show::create(['whatnot_channel_id' => $channel->id, 'title' => 'S', 'show_date' => now()->toDateString(), 'status' => 'reconciled']);

        $this->actingAs($this->owner());

        Livewire::test(AppSettings::class)
            ->mountAction('clearDemoData')
            ->setActionData(['confirm' => 'delete'])
            ->callMountedAction()
            ->assertHasActionErrors(['confirm']);

        $this->assertEquals(1, Show::count(), 'Data must survive an incorrect confirmation');
    }

    public function test_typing_delete_clears_operational_data(): void
    {
        $channel = WhatnotChannel::create(['name' => 'Breaks', 'status' => 'active']);
        Show::create(['whatnot_channel_id' => $channel->id, 'title' => 'S', 'show_date' => now()->toDateString(), 'status' => 'reconciled']);

        $this->actingAs($this->owner());

        Livewire::test(AppSettings::class)
            ->mountAction('clearDemoData')
            ->setActionData(['confirm' => 'DELETE'])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertEquals(0, Show::count());
        // Channels are explicitly preserved by demo:clear.
        $this->assertEquals(1, WhatnotChannel::count());
    }

    public function test_admin_who_is_not_owner_cannot_see_the_action(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create(['email' => 'notowner@vortex.com']);
        $admin->assignRole('admin');
        $this->actingAs($admin);

        Livewire::test(AppSettings::class)
            ->assertActionHidden('clearDemoData');
    }
}
