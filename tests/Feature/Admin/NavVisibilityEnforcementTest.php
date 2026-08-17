<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Support\NavVisibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Page visibility set on Roles & Permissions has to actually shut the door.
 *
 * It did not. The check lived in each class's canAccess(), supplied by the
 * HasModuleAccess trait — so any class writing its own canAccess() replaced the
 * trait's and dropped the check, and a class with neither was never gated. The
 * sidebar link disappeared and the URL kept working.
 *
 * These walk every navigable class rather than a sample, so a page added later
 * that reintroduces the gap fails here rather than shipping.
 */
class NavVisibilityEnforcementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Panel plumbing rather than product pages — visibility does not apply.
     * Taken from the resource so the tests and the UI cannot disagree about
     * which pages a role controls.
     */
    private const EXEMPT = \App\Filament\Resources\RoleResource::NOT_ROLE_CONTROLLED;

    private function roleUser(string $role = 'reviewer'): User
    {
        Role::findOrCreate($role, 'web');

        $user = User::factory()->create(['email' => "{$role}@example.test"]);
        $user->assignRole($role);

        return $user->fresh();
    }

    /** @return array<int, class-string> every resource and page in the panel */
    private function navigableClasses(): array
    {
        $panel = filament()->getPanel('admin');
        filament()->setCurrentPanel($panel);

        return array_values(array_diff(
            [...$panel->getResources(), ...$panel->getPages()],
            self::EXEMPT,
        ));
    }

    public function test_hiding_a_page_closes_its_route_for_that_role(): void
    {
        $user = $this->roleUser();
        $this->actingAs($user);

        $stillOpen = [];

        foreach ($this->navigableClasses() as $class) {
            NavVisibility::setHiddenForRole('reviewer', [$class]);
            NavVisibility::flushMemo();

            // The middleware is what enforces this now, so ask it directly
            // rather than canAccess() — which is deliberately left alone.
            $blocked = $this->routeIsBlocked($class, $user);

            NavVisibility::setHiddenForRole('reviewer', []);
            NavVisibility::flushMemo();

            if (! $blocked) {
                $stillOpen[] = class_basename($class);
            }
        }

        $this->assertSame(
            [],
            $stillOpen,
            'Hidden for this role but the route still opened: ' . implode(', ', $stillOpen),
        );
    }

    public function test_a_visible_page_is_not_blocked(): void
    {
        $user = $this->roleUser();
        $this->actingAs($user);

        NavVisibility::setHiddenForRole('reviewer', []);
        NavVisibility::flushMemo();

        $this->assertFalse(
            $this->routeIsBlocked(\App\Filament\Resources\InventoryItemResource::class, $user),
            'Nothing is hidden, so the gate should let this through.',
        );
    }

    public function test_the_owner_is_never_blocked_by_a_role_setting(): void
    {
        $owner = User::factory()->create(['email' => config('app.owner_email')]);
        Role::findOrCreate('reviewer', 'web');
        $owner->assignRole('reviewer');
        $owner = $owner->fresh();

        $this->actingAs($owner);

        NavVisibility::setHiddenForRole('reviewer', [\App\Filament\Resources\InventoryItemResource::class]);
        NavVisibility::flushMemo();

        $this->assertFalse($this->routeIsBlocked(\App\Filament\Resources\InventoryItemResource::class, $owner));
    }

    public function test_the_roles_page_flags_pages_that_also_gate_in_code(): void
    {
        $owner = User::factory()->create(['email' => config('app.owner_email')]);
        $this->actingAs($owner->fresh());

        // Checking Visible on one of these does not necessarily grant it, so
        // the row has to say so rather than reading as a working switch.
        \Livewire\Livewire::test(\App\Filament\Resources\RoleResource\Pages\CreateRole::class)
            ->assertOk()
            ->assertSee('code rule', false);

        $this->assertTrue(
            \App\Filament\Resources\RoleResource::hasOwnAccessRule(\App\Filament\Pages\InventoryAnalytics::class),
            'InventoryAnalytics writes its own canAccess() and should be flagged.',
        );
        $this->assertFalse(
            \App\Filament\Resources\RoleResource::hasOwnAccessRule(\App\Filament\Pages\InventoryAge::class),
            'InventoryAge takes canAccess() from the trait and should not be flagged.',
        );
    }

    public function test_hiding_a_resource_also_closes_its_child_pages(): void
    {
        $user = $this->roleUser();
        $this->actingAs($user);

        NavVisibility::setHiddenForRole('reviewer', [\App\Filament\Resources\InventoryItemResource::class]);
        NavVisibility::flushMemo();

        // The sidebar lists the resource, but the route resolves to one of its
        // pages — hiding the parent has to reach the children.
        $this->assertTrue(
            $this->routeIsBlocked(\App\Filament\Resources\InventoryItemResource\Pages\ListInventoryItems::class, $user),
            'Hiding the resource left its list page reachable.',
        );
    }

    /**
     * Runs the request through the middleware exactly as a real one would,
     * with a route whose controller is the class under test.
     */
    private function routeIsBlocked(string $class, User $user): bool
    {
        $request = \Illuminate\Http\Request::create('/admin/probe');
        $request->setUserResolver(fn () => $user);
        $request->setRouteResolver(fn () => new \Illuminate\Routing\Route(
            ['GET'],
            '/admin/probe',
            ['controller' => $class],
        ));

        try {
            app(\App\Http\Middleware\EnforceNavVisibility::class)
                ->handle($request, fn () => response('ok'));
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return $e->getStatusCode() === 403;
        }

        return false;
    }
}
