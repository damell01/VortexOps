<?php

namespace Tests\Feature\Reliability;

use App\Filament\Pages\FulfillmentPreview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FulfillmentPreviewTest extends TestCase
{
    use RefreshDatabase;

    private function asRole(string $role): User
    {
        Role::findOrCreate($role, 'web');
        $user = User::factory()->create(); $user->assignRole($role); $this->actingAs($user);
        return $user;
    }

    public function test_admin_can_preview_all_work_and_staff_scope(): void
    {
        $this->asRole('fulfillment_admin');
        Livewire::test(FulfillmentPreview::class)->assertSee('Team workload')->assertSee('Weekend Card Deals')
            ->call('setMode','staff')->assertDontSee('Team workload')->assertDontSee('Weekend Card Deals')->assertSee('Collector Showcase')
            ->call('openWork',2)->assertSee('Picking list')->call('advanceDemo',2)->assertHasNoErrors();
    }

    public function test_staff_cannot_switch_roles_or_open_unassigned_sample_work(): void
    {
        $this->asRole('fulfillment');
        Livewire::test(FulfillmentPreview::class)->set('mode','admin')->assertDontSee('Team workload')->assertDontSee('Weekend Card Deals')
            ->call('setMode','admin')->assertForbidden();
        Livewire::test(FulfillmentPreview::class)->call('openWork',4)->assertForbidden();
        Livewire::test(FulfillmentPreview::class)->call('assignDemo',2,'Morgan')->assertForbidden();
    }

    public function test_streamer_cannot_access_fulfillment_preview(): void
    {
        $this->asRole('streamer');
        $this->assertFalse(FulfillmentPreview::canAccess());
    }
}
