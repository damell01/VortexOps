<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\RoleResource;
use App\Models\User;
use App\Support\NavVisibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Every role keeps the dashboard, its profile page and its 2FA settings.
 *
 * These are not offered on the Roles screen, so a saved role's allow-list
 * never names them — and an allow-list denies whatever it does not name. That
 * combination locked users out of the application entirely: login redirects to
 * the dashboard, and the dashboard returned 403.
 *
 * A lockout is the worst failure this system can produce, so it gets a test
 * that goes through the real HTTP stack rather than only asking the gate.
 */
class DashboardStaysAvailableTest extends TestCase
{
    use RefreshDatabase;

    private function savedRoleUser(string $role = 'reviewer'): User
    {
        Role::findOrCreate($role, 'web');

        $user = User::factory()->create(['email' => "{$role}-dash@example.test"]);
        $user->assignRole($role);

        filament()->setCurrentPanel(filament()->getPanel('admin'));

        // Exactly what saving the role on the Roles screen writes.
        NavVisibility::setVisibleForRole($role, RoleResource::roleControlledPages());
        NavVisibility::flushMemo();

        return $user->fresh();
    }

    public static function alwaysAvailablePages(): array
    {
        return array_map(
            fn (string $class): array => [$class],
            NavVisibility::ALWAYS_AVAILABLE,
        );
    }

    #[DataProvider('alwaysAvailablePages')]
    public function test_a_saved_role_still_has_it(string $class): void
    {
        $user = $this->savedRoleUser();

        $this->assertFalse(
            NavVisibility::isHiddenForUser($class, $user),
            class_basename($class) . ' must stay available whatever a role is saved as.',
        );
    }

    public function test_a_role_that_can_see_nothing_can_still_reach_the_dashboard(): void
    {
        $user = $this->savedRoleUser();

        // The harshest configuration the screen can produce: everything unticked.
        NavVisibility::setVisibleForRole('reviewer', []);
        NavVisibility::flushMemo();

        $this->actingAs($user)
            ->get(\App\Filament\Pages\DashboardImproved::getUrl(panel: 'admin'))
            ->assertOk();
    }

    public function test_the_roles_screen_and_the_gate_agree_on_what_is_exempt(): void
    {
        // Two lists that must match exactly. Where the screen omits a page the
        // gate does not exempt, that page is denied to every role that saves.
        $this->assertSame(
            NavVisibility::ALWAYS_AVAILABLE,
            RoleResource::NOT_ROLE_CONTROLLED,
        );

        filament()->setCurrentPanel(filament()->getPanel('admin'));

        foreach (NavVisibility::ALWAYS_AVAILABLE as $class) {
            $this->assertNotContains(
                $class,
                RoleResource::roleControlledPages(),
                class_basename($class) . ' is exempt, so it must not also be offered as a toggle.',
            );
        }
    }
}
