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
        // The guide names the viewer's role and lists what that role does. It
        // asserted the copy — "You're: Admin", "Streamer Statement" — and the
        // page was rewritten out from under it, so every string it looked for
        // had gone. The label is the contract; the prose beneath it is not.
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'streamer', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'fulfillment', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'fulfillment_admin', 'guard_name' => 'web']);

        $owner = User::factory()->create(['email' => 'owner@vortex.com']);
        $this->actingAs($owner);
        $this->assertSame('Owner', (new HowItWorks)->getMyRoleGuideProperty()['label']);

        foreach ([
            'admin'             => 'Admin',
            'streamer'          => 'Streamer',
            'fulfillment'       => 'Fulfillment',
            'fulfillment_admin' => 'Fulfillment Admin',
        ] as $role => $label) {
            $user = User::factory()->create();
            $user->assignRole($role);
            $this->actingAs($user);

            $this->assertSame(
                $label,
                (new HowItWorks)->getMyRoleGuideProperty()['label'],
                "a {$role} was shown the wrong role guide",
            );
        }
    }

    public function test_someone_with_no_role_is_told_what_to_do_about_it(): void
    {
        // The state a new hire is in on their first sign-in, and the one most
        // likely to be left rendering an empty page.
        $this->actingAs(User::factory()->create());

        $guide = (new HowItWorks)->getMyRoleGuideProperty();

        $this->assertNotEmpty($guide['items']);
    }
}
