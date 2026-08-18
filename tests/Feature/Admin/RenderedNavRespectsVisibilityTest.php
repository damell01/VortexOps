<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Support\NavVisibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The sibling nav tests assert shouldRegisterNavigation() returns false. This
 * one asserts the link is actually absent from the page a user is served,
 * which is the thing being reported when someone says a hidden page is still
 * in their sidebar.
 *
 * It also covers the QuickCreate (+) menu, a second nav surface that the
 * boolean tests never touched: it built its list from every panel resource and
 * filtered only on canCreate(), so a hidden resource still offered a create
 * entry and a non-owner admin was offered "create role", which 403s.
 *
 * Each case makes exactly ONE request with the setting already in place.
 * Filament memoises navigation per panel instance, so flipping the setting
 * between two requests in the same process serves the first request's sidebar
 * again and reads as a bug that does not exist in production, where every
 * request is a fresh process.
 */
class RenderedNavRespectsVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private const TARGET = \App\Filament\Resources\InventoryItemResource::class;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DemoDataSeeder::class);

        Role::findOrCreate('admin', 'web');
        $user = User::factory()->create(['email' => 'rendered-nav@example.test']);
        $user->assignRole('admin');

        $this->actingAs($user->fresh());
    }

    private function dashboard(): string
    {
        return \App\Filament\Pages\DashboardImproved::getUrl(panel: 'admin');
    }

    /** Rendered hrefs are absolute, so match the full URL and its exact quote. */
    private function sidebarLink(): string
    {
        return 'href="' . self::TARGET::getUrl('index', panel: 'admin') . '"';
    }

    private function quickCreateLink(): string
    {
        return 'href="' . self::TARGET::getUrl('index', panel: 'admin') . '/create"';
    }

    private function hide(array $classes): void
    {
        NavVisibility::setHiddenForRole('admin', $classes);
        NavVisibility::flushMemo();
    }

    public function test_the_link_is_rendered_when_the_page_is_visible(): void
    {
        $this->hide([]);

        $this->get($this->dashboard())
            ->assertOk()
            ->assertSee($this->sidebarLink(), false);
    }

    public function test_the_link_is_gone_when_the_page_is_hidden(): void
    {
        $this->hide([self::TARGET]);

        $this->get($this->dashboard())
            ->assertOk()
            ->assertDontSee($this->sidebarLink(), false);
    }

    public function test_the_quick_create_entry_goes_with_it(): void
    {
        $this->hide([self::TARGET]);

        // The + menu is built separately from the sidebar and used to ignore
        // the setting entirely.
        $this->get($this->dashboard())
            ->assertOk()
            ->assertDontSee($this->quickCreateLink(), false);
    }

    public function test_quick_create_does_not_offer_pages_the_user_cannot_open(): void
    {
        $this->hide([]);

        // Roles is owner-only; this user is an admin, so a "create role" entry
        // would 403 the moment it was clicked.
        $this->get($this->dashboard())
            ->assertOk()
            ->assertDontSee('/admin/roles/create', false);
    }
}
