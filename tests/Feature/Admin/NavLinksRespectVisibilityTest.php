<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\RoleResource;
use App\Models\Streamer;
use App\Models\User;
use App\Support\NavVisibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Answers one question directly: does every sidebar link honour the Visible
 * setting on Roles & Permissions?
 *
 * Sibling tests cover the other halves — NavVisibilityEnforcementTest that a
 * hidden page's route closes, SidebarMatchesAccessTest that no link 403s. This
 * one is about the link disappearing when it should.
 *
 * The exemptions come from RoleResource::NOT_ROLE_CONTROLLED rather than a list
 * kept here, so a page cannot be exempt in the tests while still being offered
 * as a switch in the UI.
 */
class NavLinksRespectVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DemoDataSeeder::class);
    }

    private function userWithRole(string $role): User
    {
        Role::findOrCreate($role, 'web');

        $user = User::factory()->create(['email' => "{$role}-visibility@example.test"]);
        $user->assignRole($role);

        if ($role === 'streamer' && ($streamer = Streamer::first())) {
            $streamer->forceFill(['user_id' => $user->id])->save();
        }

        return $user->fresh();
    }

    public static function roles(): array
    {
        return [
            'admin'       => ['admin'],
            'streamer'    => ['streamer'],
            'fulfillment' => ['fulfillment'],
        ];
    }

    #[DataProvider('roles')]
    public function test_every_visible_link_disappears_when_hidden_for_the_role(string $role): void
    {
        $this->actingAs($this->userWithRole($role));

        $panel = filament()->getPanel('admin');
        filament()->setCurrentPanel($panel);

        $ignored = [];
        $checked = 0;

        foreach ([...$panel->getResources(), ...$panel->getPages()] as $class) {
            if (in_array($class, RoleResource::NOT_ROLE_CONTROLLED, true)) {
                continue;
            }

            NavVisibility::setHiddenForRole($role, []);
            NavVisibility::flushMemo();

            try {
                // Only links this role is shown in the first place can be
                // tested for disappearing.
                if (! $class::shouldRegisterNavigation()) {
                    continue;
                }
            } catch (\Throwable) {
                continue;
            }

            $checked++;

            NavVisibility::setHiddenForRole($role, [$class]);
            NavVisibility::flushMemo();

            try {
                $stillShown = $class::shouldRegisterNavigation();
            } catch (\Throwable) {
                $stillShown = true;
            }

            if ($stillShown) {
                $ignored[] = class_basename($class);
            }
        }

        NavVisibility::setHiddenForRole($role, []);
        NavVisibility::flushMemo();

        $this->assertGreaterThan(0, $checked, "No links were shown to '{$role}' at all — nothing was tested.");

        $this->assertSame(
            [],
            $ignored,
            "Links shown to '{$role}' that ignore the Visible setting: " . implode(', ', $ignored),
        );
    }

    public function test_the_roles_page_does_not_offer_switches_it_cannot_honour(): void
    {
        // The seeders already create the owner account.
        $owner = User::firstWhere('email', config('app.owner_email'))
            ?? User::factory()->create(['email' => config('app.owner_email')]);

        $this->actingAs($owner->fresh());

        $offered = array_keys(RoleResource::pageOptions());

        foreach (RoleResource::NOT_ROLE_CONTROLLED as $exempt) {
            $this->assertNotContains(
                $exempt,
                $offered,
                class_basename($exempt) . ' is not role-controlled, so it must not appear as a toggle.',
            );
        }
    }
}
