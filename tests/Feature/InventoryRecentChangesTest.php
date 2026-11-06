<?php

namespace Tests\Feature;

use App\Livewire\InventoryRecentChanges;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InventoryRecentChangesTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_open_call_registers_a_dialog_and_it_can_close_and_reopen(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('streamer', 'web'));
        Livewire::actingAs($user)->test(InventoryRecentChanges::class)
            ->assertSet('open', false)
            ->call('show')->assertSet('open', true)
            ->assertSeeHtml('data-vx-dialog')
            ->assertSee('Close recent changes')->assertSee('Done')
            ->call('close')->assertSet('open', false)
            ->call('show')->assertSet('open', true);
    }

    public function test_assistants_cannot_open_the_inventory_dialog(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('streamer_assistant', 'web'));
        Livewire::actingAs($user)->test(InventoryRecentChanges::class)
            ->call('show')->assertForbidden();
    }
}
