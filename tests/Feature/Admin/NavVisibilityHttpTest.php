<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Support\NavVisibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The companion to NavVisibilityEnforcementTest, which exercises the middleware
 * directly. This one goes through the real HTTP stack, so it also asserts the
 * middleware is actually registered on the panel — a check the unit-level test
 * would keep passing even if it were never wired up.
 */
class NavVisibilityHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DemoDataSeeder::class);
    }

    public static function gatedPages(): array
    {
        return [
            'a page'     => [\App\Filament\Pages\InventoryAnalytics::class, null],
            'a resource' => [\App\Filament\Resources\InventoryItemResource::class, 'index'],
        ];
    }

    private function adminUser(): User
    {
        Role::findOrCreate('admin', 'web');

        $user = User::factory()->create(['email' => 'admin-probe@example.test']);
        $user->assignRole('admin');

        return $user->fresh();
    }

    private function urlFor(string $class, ?string $page): string
    {
        return $page === null
            ? $class::getUrl(panel: 'admin')
            : $class::getUrl($page, panel: 'admin');
    }

    #[DataProvider('gatedPages')]
    public function test_a_visible_page_loads(string $class, ?string $page): void
    {
        $this->actingAs($this->adminUser());

        NavVisibility::setHiddenForRole('admin', []);
        NavVisibility::flushMemo();

        $this->get($this->urlFor($class, $page))->assertOk();
    }

    #[DataProvider('gatedPages')]
    public function test_hiding_it_for_the_role_returns_403(string $class, ?string $page): void
    {
        $this->actingAs($this->adminUser());

        NavVisibility::setHiddenForRole('admin', [$class]);
        NavVisibility::flushMemo();

        // Not merely absent from the sidebar — the URL itself has to close.
        $this->get($this->urlFor($class, $page))->assertForbidden();
    }
}
