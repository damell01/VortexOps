<?php

namespace Tests\Feature\Admin;

use App\Models\Setting;
use App\Models\User;
use App\Support\AdminModules;
use App\Support\NavVisibility;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Ticking a page for a custom role has to actually open it.
 *
 * Access was decided in two places that disagreed. Roles & Permissions wrote
 * an allow-list per role; every resource and page separately hardcoded who it
 * was for, almost always isAdmin(). The hardcode won, so the screen could take
 * access away and never give it: a role granted all 82 pages, and every Shield
 * permission besides, was still refused 47 of them. The screen's own help text
 * conceded the point — "will need code changes before a custom role can safely
 * use it".
 *
 * An explicit grant is now the answer, and the hardcoded check is the fallback
 * for roles with no explicit list. Every built-in role is in that second group,
 * so admin, streamer and fulfillment are untouched.
 *
 * Two things a grant still cannot do, both deliberate: reach into a disabled
 * module, and change what a query returns. Row scoping stays in
 * getEloquentQuery().
 */
class CustomRoleGrantsAreObeyedTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<int, class-string> */
    private function panelClasses(): array
    {
        $panel = Filament::getPanel('admin');
        Filament::setCurrentPanel($panel);

        return array_merge(array_values($panel->getResources()), array_values($panel->getPages()));
    }

    private function customRoleUser(array $granted): User
    {
        Role::findOrCreate('warehouse', 'web');

        $user = User::factory()->create(['email' => 'warehouse@example.com']);
        $user->assignRole('warehouse');

        NavVisibility::setVisibleForRole('warehouse', $granted);
        NavVisibility::flushMemo();

        return $user->fresh();
    }

    private function enableEveryModule(): void
    {
        Setting::set('enabled_admin_modules', json_encode(AdminModules::defaultEnabledSlugs()));
        AdminModules::flushMemo();
    }

    public function test_granting_a_page_opens_it(): void
    {
        $this->enableEveryModule();
        $this->actingAs($this->customRoleUser([
            \App\Filament\Resources\PalletResource::class,
            \App\Filament\Resources\VendorResource::class,
        ]));

        $this->assertTrue(\App\Filament\Resources\PalletResource::canAccess());
        $this->assertTrue(\App\Filament\Resources\VendorResource::canAccess());
    }

    public function test_not_granting_a_page_leaves_it_shut(): void
    {
        $this->enableEveryModule();
        $this->actingAs($this->customRoleUser([\App\Filament\Resources\PalletResource::class]));

        $this->assertFalse(\App\Filament\Resources\StreamerLoanResource::canAccess());
        $this->assertFalse(\App\Filament\Resources\WhatnotChannelResource::canAccess());
    }

    public function test_a_role_granted_everything_is_refused_nothing_that_is_switched_on(): void
    {
        // The headline number. Anything still refused here is a page whose
        // module is off, which is the owner turning off an area of the
        // product — not a role decision, and not this screen's business.
        $this->enableEveryModule();

        $classes = $this->panelClasses();
        $this->actingAs($this->customRoleUser($classes));

        $denied = [];

        foreach ($classes as $class) {
            if (! rescue(fn () => $class::canAccess(), false, false)) {
                $denied[] = class_basename($class);
            }
        }

        // fulfillment and timekeeping ship switched off, so their pages stay
        // shut however the roles are configured.
        sort($denied);
        $this->assertSame(['FulfillmentResource', 'Timekeeping'], $denied);
    }

    public function test_a_grant_cannot_reach_into_a_module_that_is_off(): void
    {
        Setting::set('enabled_admin_modules', json_encode(['streams']));
        AdminModules::flushMemo();

        $this->actingAs($this->customRoleUser([\App\Filament\Resources\PalletResource::class]));

        $this->assertFalse(
            \App\Filament\Resources\PalletResource::canAccess(),
            'a per-role grant overrode the owner switching off a whole module',
        );
    }

    public function test_a_granted_page_also_gets_its_sidebar_link(): void
    {
        // Access with no way to reach it is half a grant. Four resources
        // hardcoded roles in shouldRegisterNavigation() separately from
        // canAccess(), so a granted role could open a page it had no link to.
        $this->enableEveryModule();
        $this->actingAs($this->customRoleUser([\App\Filament\Resources\StreamerLogResource::class]));

        $this->assertTrue(\App\Filament\Resources\StreamerLogResource::shouldRegisterNavigation());
    }

    public function test_the_built_in_roles_are_untouched(): void
    {
        // They have no explicit list, so nothing about them changes — which is
        // the point of making the grant the exception rather than the rule.
        $this->enableEveryModule();

        Role::findOrCreate('streamer', 'web');
        $streamer = User::factory()->create(['email' => 'streamer@example.com']);
        $streamer->assignRole('streamer');

        $this->actingAs($streamer->fresh());

        $this->assertTrue(\App\Filament\Resources\ShowResource::canAccess());
        $this->assertFalse(\App\Filament\Resources\StreamerLoanResource::canAccess());
        $this->assertFalse(\App\Filament\Resources\WhatnotChannelResource::canAccess());
    }

    public function test_the_owner_is_never_narrowed_by_a_grant_list(): void
    {
        $this->enableEveryModule();

        Role::findOrCreate('warehouse', 'web');
        NavVisibility::setVisibleForRole('warehouse', []);
        NavVisibility::flushMemo();

        $owner = User::factory()->create(['email' => 'dbellcreations@gmail.com']);
        $owner->assignRole('warehouse');

        $this->actingAs($owner->fresh());

        $this->assertTrue(\App\Filament\Resources\StreamerLoanResource::canAccess());
    }

    public function test_an_explicit_list_is_exhaustive(): void
    {
        // The other half of "follow the screen exactly". Thirteen pages had no
        // access check of any kind, and two more answered `return true` and
        // `auth()->check()`, so a role granted only Pallets still reached
        // StreamerHub, Manager Hub, both scanners, Quick Add Stock and the
        // inventory report. A role with an explicit list now gets what is on
        // it and the handful of pages nobody is ever locked out of.
        $this->enableEveryModule();
        $this->actingAs($this->customRoleUser([\App\Filament\Resources\PalletResource::class]));

        $allowed = [];

        foreach ($this->panelClasses() as $class) {
            if (rescue(fn () => $class::canAccess(), false, false)) {
                $allowed[] = class_basename($class);
            }
        }

        sort($allowed);

        $this->assertSame([
            'DashboardImproved',
            'EditProfile',
            'PalletResource',
            'TwoFactorAuth',
            'TwoFactorVerify',
        ], $allowed);
    }

    public function test_a_role_with_no_list_keeps_everything_it_had(): void
    {
        // Built-in roles have no explicit list, so tightening the ungated
        // pages must not touch them. A streamer reaches the same pages as
        // before.
        $this->enableEveryModule();

        Role::findOrCreate('streamer', 'web');
        $streamer = User::factory()->create(['email' => 'unrestricted@example.com']);
        $streamer->assignRole('streamer');

        $this->actingAs($streamer->fresh());

        foreach ([
            \App\Filament\Pages\StreamerHub::class,
            \App\Filament\Pages\StreamerShows::class,
            \App\Filament\Pages\QuickAddStock::class,
            \App\Filament\Pages\InventoryReport::class,
            \App\Filament\Pages\HowItWorks::class,
        ] as $class) {
            $this->assertTrue($class::canAccess(), class_basename($class) . ' closed for a role with no explicit list');
        }
    }
}
