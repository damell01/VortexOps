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
 * A role granted everything must be able to use everything.
 *
 * The gate resolved a route to every class it touched — for a resource page
 * that is the List/Edit page AND the resource — and denied if any of them was
 * hidden. Only the resource is ever named in an allow-list, so every resource
 * page 403'd. The logout route was worse: its controller is not a page at all,
 * so nothing could ever name it, and signing out became impossible.
 *
 * An allow-list refuses whatever it does not name, which makes "what is this
 * gate allowed to judge?" the load-bearing question. It judges only the pages
 * the Roles screen offers; everything else is infrastructure and passes.
 */
class RoleGateDoesNotBreakTheAppTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DemoDataSeeder::class);

        Role::findOrCreate('admin', 'web');
        $user = User::factory()->create(['email' => 'gate@example.test']);
        $user->assignRole('admin');

        $this->actingAs($user->fresh());
        filament()->setCurrentPanel(filament()->getPanel('admin'));

        // What saving the admin role on the Roles screen writes: all ticked.
        NavVisibility::setVisibleForRole('admin', RoleResource::roleControlledPages());
        NavVisibility::flushMemo();
        $this->enableAdminModules();
    }


    public static function grantedPages(): array
    {
        return [
            'inventory items' => [\App\Filament\Resources\InventoryItemResource::class],
            'shows'           => [\App\Filament\Resources\ShowResource::class],
            'vendors'         => [\App\Filament\Resources\VendorResource::class],
        ];
    }

    #[DataProvider('grantedPages')]
    public function test_a_granted_resource_opens(string $resource): void
    {
        // Granting the resource has to open the page the route resolves to,
        // not just the resource class nobody navigates to directly.
        $this->get($resource::getUrl('index', panel: 'admin'))->assertOk();
    }

    public function test_the_gate_lets_the_logout_route_through(): void
    {
        // Driven through the middleware rather than by POSTing the route.
        // Actually completing a logout mid-suite tears down the session and
        // the database connection with it, which surfaced as a dozen unrelated
        // failures in whatever happened to run next.
        $route = collect(app('router')->getRoutes())
            ->first(fn ($r) => $r->getName() === 'filament.admin.auth.logout');

        $this->assertNotNull($route, 'The logout route should exist.');

        $request = \Illuminate\Http\Request::create('/admin/logout', 'POST');
        $request->setUserResolver(fn () => auth()->user());
        $request->setRouteResolver(fn () => new \Illuminate\Routing\Route(
            ['POST'],
            '/admin/logout',
            ['controller' => $route->getAction('controller')],
        ));

        $reached = false;

        app(\App\Http\Middleware\EnforceNavVisibility::class)
            ->handle($request, function () use (&$reached) {
                $reached = true;

                return response('ok');
            });

        // Logout is not a page, so no allow-list can ever name it. Judging it
        // against one denied it outright and left users unable to sign out.
        $this->assertTrue($reached, 'The gate blocked the logout route.');
    }

    public function test_the_dashboard_opens(): void
    {
        $this->get(\App\Filament\Pages\DashboardImproved::getUrl(panel: 'admin'))->assertOk();
    }

    public function test_a_withheld_resource_still_closes(): void
    {
        // The gate must still do its job — this is the behaviour the whole
        // feature exists for, and the fix above must not have opened it back up.
        NavVisibility::setVisibleForRole('admin', [\App\Filament\Resources\ShowResource::class]);
        NavVisibility::flushMemo();

        $this->get(\App\Filament\Resources\InventoryItemResource::getUrl('index', panel: 'admin'))
            ->assertForbidden();

        $this->get(\App\Filament\Resources\ShowResource::getUrl('index', panel: 'admin'))
            ->assertOk();
    }

    public function test_withholding_a_resource_also_closes_its_child_pages(): void
    {
        NavVisibility::setVisibleForRole('admin', []);
        NavVisibility::flushMemo();

        $item = \App\Models\InventoryItem::firstOrFail();

        $this->get(\App\Filament\Resources\InventoryItemResource::getUrl('edit', ['record' => $item], panel: 'admin'))
            ->assertForbidden();
    }
}
