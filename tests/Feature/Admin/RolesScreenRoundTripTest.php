<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\RoleResource;
use App\Filament\Resources\RoleResource\Pages\EditRole;
use App\Models\User;
use App\Support\NavVisibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * What the Roles screen shows must be what the gate enforces.
 *
 * The existing sweep wrote the visible list straight into NavVisibility, which
 * proves the gate agrees with a correctly saved list but says nothing about
 * whether saving produces one. The reported failure lives exactly in that gap:
 * a page ticked Visible on the screen that still answers 403. So this goes
 * through the form — fill, save, read back — and then opens the pages.
 */
class RolesScreenRoundTripTest extends TestCase
{
    use RefreshDatabase;

    private Role $role;
    private User $member;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DemoDataSeeder::class);
        NavVisibility::flushMemo();
        config(['app.owner_email' => 'dbellcreations@gmail.com']);

        $this->role = Role::findOrCreate('admin', 'web');

        // Two people, as in life: the owner configures the Roles screen, and
        // somebody holding that role then tries to use the pages. Testing both
        // as one user hides the bug entirely, because the owner is exempt from
        // the gate and never sees a 403 whatever the screen says.
        $this->member = User::factory()->create(['email' => 'roundtrip@example.test']);
        $this->member->assignRole('admin');
        $this->enableAdminModules();
    }


    /** Act as the owner, who is the only one allowed to edit roles. */
    private function owner(): User
    {
        return User::firstWhere('email', 'dbellcreations@gmail.com')
            ?? User::factory()->create(['email' => 'dbellcreations@gmail.com']);
    }

    /** Act as somebody holding the role, and view the panel as they do. */
    private function asMember(): void
    {
        $this->actingAs($this->member->fresh());
        filament()->setCurrentPanel(filament()->getPanel('admin'));
    }

    /**
     * Save the Roles screen with every page ticked Visible.
     *
     * Driven through fillForm rather than by setting the state array wholesale,
     * so the values travel the same path a person's clicks do.
     *
     * @param array<class-string, bool> $overrides
     */
    private function saveVisibility(array $overrides = []): void
    {
        $form = [];

        foreach (RoleResource::roleControlledPages() as $class) {
            $key = RoleResource::pageKey($class);

            $form["page_perms.{$key}.visible"]  = $overrides[$class] ?? true;
            $form["page_perms.{$key}.editable"] = true;
        }

        Livewire::actingAs($this->owner());

        Livewire::test(EditRole::class, ['record' => $this->role->getRouteKey()])
            ->fillForm($form)
            ->call('save')
            ->assertHasNoFormErrors();

        NavVisibility::flushMemo();
    }

    public function test_saving_with_everything_ticked_grants_everything(): void
    {
        $this->saveVisibility();

        $missing = array_values(array_diff(
            RoleResource::roleControlledPages(),
            NavVisibility::visibleForRole('admin'),
        ));

        $this->assertSame(
            [],
            $missing,
            "Ticked Visible on the Roles screen but absent from the saved list:\n" . implode("\n", $missing)
        );
    }

    public function test_what_the_screen_shows_after_saving_matches_what_was_saved(): void
    {
        // The screen re-reads its own state on the next visit. If it renders a
        // tick the saved list does not back, the report "the role has it as
        // visible" is true of the screen and false of the gate — which is the
        // shape of the bug being chased.
        $this->saveVisibility();

        $state = RoleResource::pagePermsFormState('admin', NavVisibility::readonlyForRole('admin'));

        $shownButNotGranted = [];

        foreach (RoleResource::roleControlledPages() as $class) {
            $ticked  = $state[RoleResource::pageKey($class)]['visible'] ?? false;
            $granted = in_array($class, NavVisibility::visibleForRole('admin'), true);

            if ($ticked && ! $granted) {
                $shownButNotGranted[] = $class;
            }
        }

        $this->assertSame([], $shownButNotGranted, implode("\n", $shownButNotGranted));
    }

    public function test_every_page_ticked_on_the_screen_actually_opens(): void
    {
        $this->saveVisibility();
        $this->asMember();

        // Withheld by an owner/developer/streamer check or a disabled module
        // rather than by visibility — the same set the direct-write sweep
        // enumerates, and not what this test is looking for.
        $explained = [
            \App\Filament\Pages\DemoData::class,
            \App\Filament\Pages\WhatnotBackfill::class,
            \App\Filament\Pages\AiMonitoring::class,
            \App\Filament\Resources\FeatureFlagResource::class,
            \App\Filament\Pages\Timekeeping::class,
            \App\Filament\Resources\FulfillmentResource::class,
            \App\Filament\Pages\StreamerShows::class,
            \App\Filament\Pages\StreamerProfitShare::class,
            \App\Filament\Pages\StreamerHub::class,
        ];

        $denied = [];

        foreach (RoleResource::roleControlledPages() as $class) {
            if (in_array($class, $explained, true)) {
                continue;
            }

            try {
                $url = is_subclass_of($class, \Filament\Resources\Resource::class)
                    ? $class::getUrl('index', panel: 'admin')
                    : $class::getUrl(panel: 'admin');
            } catch (\Throwable) {
                continue;
            }

            if ($this->get($url)->getStatusCode() === 403) {
                $denied[] = $class;
            }
        }

        $this->assertSame(
            [],
            $denied,
            "Ticked Visible and saved, and still 403:\n" . implode("\n", $denied)
        );
    }

    public function test_the_grid_shows_exactly_what_is_saved_and_enforced(): void
    {
        // The load-bearing invariant. The grid and the allow-list were built by
        // two separate walks of the panel, each swallowing its own errors on a
        // different method — so a page could render with a tick beside it and
        // never reach the list the gate reads. That page then answers 403 while
        // the screen insists it is visible, which is the whole complaint.
        $grouped = [];
        foreach (RoleResource::pagesByGroup() as $pages) {
            $grouped = array_merge($grouped, array_keys($pages));
        }

        sort($grouped);
        $enforced = RoleResource::roleControlledPages();
        sort($enforced);

        $shownNotSaved = array_values(array_diff($grouped, $enforced));
        $savedNotShown = array_values(array_diff($enforced, $grouped));

        $this->assertSame([], $shownNotSaved, "Ticked on the screen but never saved:\n" . implode("\n", $shownNotSaved));
        $this->assertSame([], $savedNotShown, "Enforced but impossible to tick:\n" . implode("\n", $savedNotShown));
    }

    public function test_unticking_one_page_closes_only_that_page(): void
    {
        // The gate must still work after a real save, not just after a direct
        // write — otherwise this test would pass on a screen that grants
        // everything unconditionally.
        $this->saveVisibility([\App\Filament\Resources\VendorResource::class => false]);
        $this->asMember();

        $this->get(\App\Filament\Resources\VendorResource::getUrl('index', panel: 'admin'))->assertForbidden();
        $this->get(\App\Filament\Resources\ShowResource::getUrl('index', panel: 'admin'))->assertOk();
    }
}
