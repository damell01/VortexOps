<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\RoleResource;
use App\Models\User;
use App\Support\NavVisibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Every page a role is granted must actually open for that role.
 *
 * The spot-check version of this covered three resources, which is how a gate
 * that 403'd most of the application shipped twice. The failure mode is not
 * "one page is wrong" — it is a rule that misjudges a whole category of route
 * at once — so this walks the entire list the Roles screen offers rather than
 * a sample of it, and reports every page that closed, not just the first.
 *
 * A page is allowed to redirect, 404 on a missing record, or refuse on its own
 * business rules. The only outcome under test is 403 from the visibility gate,
 * because that is the one that means "your role cannot have this" about a page
 * the role was explicitly given.
 */
class EveryGrantedPageOpensTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DemoDataSeeder::class);

        Role::findOrCreate('admin', 'web');
        $user = User::factory()->create(['email' => 'everything@example.test']);
        $user->assignRole('admin');

        $this->actingAs($user->fresh());
        filament()->setCurrentPanel(filament()->getPanel('admin'));
    }

    /** The URL a class is reached at, or null if it takes a record. */
    private function urlFor(string $class): ?string
    {
        try {
            if (is_subclass_of($class, \Filament\Resources\Resource::class)) {
                return $class::getUrl('index', panel: 'admin');
            }

            return $class::getUrl(panel: 'admin');
        } catch (\Throwable) {
            // Needs parameters we cannot invent; the gate is exercised by the
            // resources and parameterless pages either way.
            return null;
        }
    }

    /**
     * Pages an admin is refused whatever the Roles screen says, and why.
     *
     * These are not visibility decisions — they are the owner check, the
     * developer check, a disabled module, and pages belonging to a different
     * role entirely. Listed rather than skipped so the set cannot quietly
     * grow: a page appearing here that is not named is the gate over-reaching
     * again, which is the failure this whole test exists to catch.
     */
    private const RESTRICTED_BY_SOMETHING_ELSE = [
        \App\Filament\Pages\DemoData::class            => 'owner or super_admin only',
        \App\Filament\Pages\WhatnotBackfill::class     => 'owner only',
        \App\Filament\Pages\AiMonitoring::class        => 'owner or admin, and the ai module',
        \App\Filament\Resources\FeatureFlagResource::class => 'developer email only',
        \App\Filament\Pages\Timekeeping::class         => 'the timekeeping module is off',
        \App\Filament\Resources\FulfillmentResource::class => 'the fulfillment module is off',
        \App\Filament\Pages\StreamerShows::class       => 'streamer role only',
        \App\Filament\Pages\StreamerProfitShare::class => 'streamer role only',
        \App\Filament\Pages\StreamerHub::class         => 'streamer role only',
    ];

    /** @return array{denied: list<string>, checked: int} */
    private function sweep(): array
    {
        $pages = RoleResource::roleControlledPages();

        $this->assertNotEmpty($pages, 'No pages to check — the roles screen resolved an empty list.');

        NavVisibility::setVisibleForRole('admin', $pages);
        NavVisibility::flushMemo();

        $denied  = [];
        $checked = 0;

        foreach ($pages as $class) {
            $url = $this->urlFor($class);

            if ($url === null) {
                continue;
            }

            $checked++;

            if ($this->get($url)->getStatusCode() === 403) {
                $denied[] = $class;
            }
        }

        $this->assertGreaterThan(20, $checked, 'Too few pages were reachable to call this a sweep.');

        return ['denied' => $denied, 'checked' => $checked];
    }

    public function test_a_role_granted_everything_is_denied_only_what_another_rule_withholds(): void
    {
        $unexpected = array_values(array_diff(
            $this->sweep()['denied'],
            array_keys(self::RESTRICTED_BY_SOMETHING_ELSE),
        ));

        $this->assertSame(
            [],
            $unexpected,
            "Granted on the Roles screen and still 403'd, with no other rule to explain it:\n"
            . implode("\n", $unexpected)
        );
    }

    public function test_the_owner_granted_everything_is_denied_nothing_of_their_own(): void
    {
        // The account that actually runs this. Everything withheld above is
        // withheld by the owner check, the developer check, a disabled module,
        // or the streamer role — so for the owner, only the last two survive,
        // and a 403 anywhere else is the gate misfiring.
        // The seeder already creates this account.
        $owner = User::firstWhere('email', config('app.owner_email'))
            ?? User::factory()->create(['email' => config('app.owner_email')]);
        $owner->assignRole('admin');
        $this->actingAs($owner->fresh());

        $stillDenied = array_values(array_diff($this->sweep()['denied'], [
            // Not the owner's to open: these belong to the streamer role, and
            // modules that are switched off are off for everyone.
            \App\Filament\Pages\StreamerShows::class,
            \App\Filament\Pages\StreamerProfitShare::class,
            \App\Filament\Pages\StreamerHub::class,
            \App\Filament\Pages\Timekeeping::class,
            \App\Filament\Resources\FulfillmentResource::class,
            \App\Filament\Pages\AiMonitoring::class,
            \App\Filament\Resources\FeatureFlagResource::class,
        ]));

        $this->assertSame([], $stillDenied, "The owner was 403'd on:\n" . implode("\n", $stillDenied));
    }

    public function test_signing_out_works_for_a_role_granted_nothing(): void
    {
        // The worst version of the bug: a role with no pages could not even
        // leave. Logout is not a page, so no allow-list can name it, and a
        // gate that judges everything denies it by construction.
        NavVisibility::setVisibleForRole('admin', []);
        NavVisibility::flushMemo();

        $this->post(route('filament.admin.auth.logout'))->assertRedirect();
        $this->assertGuest();
    }

    public function test_the_dashboard_survives_a_role_granted_nothing(): void
    {
        // Somewhere to land after signing in. Without this the user is bounced
        // between a dashboard they cannot see and a login they are past.
        NavVisibility::setVisibleForRole('admin', []);
        NavVisibility::flushMemo();

        $this->get(\App\Filament\Pages\DashboardImproved::getUrl(panel: 'admin'))->assertOk();
    }

    public function test_profile_and_two_factor_survive_a_role_granted_nothing(): void
    {
        NavVisibility::setVisibleForRole('admin', []);
        NavVisibility::flushMemo();

        foreach ([\App\Filament\Pages\EditProfile::class, \App\Filament\Pages\TwoFactorAuth::class] as $page) {
            $this->get($page::getUrl(panel: 'admin'))
                ->assertStatus(200, "{$page} should stay reachable — a role cannot be locked out of its own account.");
        }
    }

    public function test_livewire_keeps_working_for_a_role_granted_nothing(): void
    {
        // Every modal, table filter and form save goes through this endpoint.
        // Judging it against the allow-list froze the whole panel: pages
        // rendered, and then nothing on them worked.
        NavVisibility::setVisibleForRole('admin', []);
        NavVisibility::flushMemo();

        $this->assertNotSame(
            403,
            $this->post('/livewire/update', [])->getStatusCode(),
            'The gate blocked Livewire, which breaks every interactive control in the panel.'
        );
    }
}
