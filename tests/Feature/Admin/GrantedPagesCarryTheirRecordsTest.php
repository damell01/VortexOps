<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\ShowResource;
use App\Models\Setting;
use App\Models\Show;
use App\Models\User;
use App\Models\WhatnotChannel;
use App\Support\AdminModules;
use App\Support\NavVisibility;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Granting a page has to grant the records on it.
 *
 * canAccess() only governs the list. Opening one row asks canView(), which
 * falls through to the policy and its Shield permission — so a role granted
 * "Shows" on Roles & Permissions opened the list and then took a 403 on every
 * show in it. The screen said yes and the record page said no.
 *
 * "Can Edit" was worse: readonlyForRole() was written by the Roles screen and
 * read back by that same screen, and nothing else ever asked. It was a
 * checkbox with no effect anywhere in the application.
 */
class GrantedPagesCarryTheirRecordsTest extends TestCase
{
    use RefreshDatabase;

    private Show $show;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::set('enabled_admin_modules', json_encode(AdminModules::defaultEnabledSlugs()));
        AdminModules::flushMemo();

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->show = Show::create([
            'whatnot_channel_id' => WhatnotChannel::create(['name' => 'Vortex', 'status' => 'active'])->id,
            'title'              => 'Break #77',
            'show_date'          => now()->subDay()->toDateString(),
        ]);
    }

    private function userWith(array $visible, array $readonly = []): User
    {
        Role::findOrCreate('fulfillment_admin', 'web');

        $user = User::factory()->create(['email' => 'fulfillment-admin@vortexbreaks.com']);
        $user->assignRole('fulfillment_admin');

        NavVisibility::setVisibleForRole('fulfillment_admin', $visible);
        NavVisibility::setReadonlyForRole('fulfillment_admin', $readonly);
        NavVisibility::flushMemo();

        return $user->fresh();
    }

    public function test_a_granted_page_opens_its_records(): void
    {
        // The reported case: Shows ticked Visible, /admin/shows/{id} → 403.
        $this->actingAs($this->userWith([ShowResource::class]));

        $this->assertTrue(ShowResource::canView($this->show));

        $this->get(ShowResource::getUrl('view', ['record' => $this->show]))
            ->assertSuccessful();
    }

    public function test_can_edit_ticked_actually_permits_editing(): void
    {
        $this->actingAs($this->userWith([ShowResource::class]));

        $this->assertTrue(ShowResource::canEdit($this->show));
    }

    public function test_can_edit_unticked_leaves_it_read_only(): void
    {
        // Visible without Can Edit is the readonly list, which is the whole
        // reason the second checkbox exists.
        $this->actingAs($this->userWith([ShowResource::class], [ShowResource::class]));

        $this->assertTrue(ShowResource::canView($this->show), 'readonly should still be able to look');
        $this->assertFalse(ShowResource::canEdit($this->show), 'readonly was allowed to edit');
    }

    public function test_a_page_that_was_never_granted_is_still_shut(): void
    {
        $this->actingAs($this->userWith([\App\Filament\Resources\PalletResource::class]));

        $this->get(ShowResource::getUrl('view', ['record' => $this->show]))
            ->assertForbidden();
    }

    public function test_a_role_with_no_list_is_decided_by_the_policy_as_before(): void
    {
        Role::findOrCreate('streamer', 'web');
        $streamer = User::factory()->create(['email' => 'streamer@example.com']);
        $streamer->assignRole('streamer');

        $this->actingAs($streamer->fresh());

        // No explicit list, so nothing here changes its answer — the policy
        // and ShowResource's own rules still decide.
        $this->assertFalse(ShowResource::canEdit($this->show));
    }
}
