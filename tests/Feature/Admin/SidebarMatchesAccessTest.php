<?php

namespace Tests\Feature\Admin;

use App\Models\Streamer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The sidebar and the access rules have to agree.
 *
 * They were written independently per class and had drifted apart in both
 * directions: pages a role could open with no link to them (ticking Visible on
 * Roles & Permissions appeared to do nothing), and links that 403'd when
 * clicked. Deriving registration from canAccess() in the traits fixes both, and
 * this is what stops it drifting again.
 */
class SidebarMatchesAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Pages that are deliberately reachable without a sidebar entry, because
     * they are opened from another page. Anything else appearing here means
     * the two rules have diverged again — add it only with a reason.
     */
    private const INTENTIONALLY_UNLISTED = [
        // Streamers open their own log from their own pages, not a top-level
        // entry. Documented on the resource.
        \App\Filament\Resources\StreamerLogResource::class,
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DemoDataSeeder::class);
    }

    private function userWithRole(string $role): User
    {
        Role::findOrCreate($role, 'web');

        $user = User::factory()->create(['email' => "{$role}-nav@example.test"]);
        $user->assignRole($role);

        if ($role === 'streamer' && ($streamer = Streamer::first())) {
            $streamer->forceFill(['user_id' => $user->id])->save();
        }

        return $user->fresh();
    }

    /** @return array<int, class-string> */
    private function navigableClasses(): array
    {
        $panel = filament()->getPanel('admin');
        filament()->setCurrentPanel($panel);

        return [...$panel->getResources(), ...$panel->getPages()];
    }

    public static function roles(): array
    {
        return [
            'streamer'    => ['streamer'],
            'admin'       => ['admin'],
            'fulfillment' => ['fulfillment'],
        ];
    }

    #[DataProvider('roles')]
    public function test_no_role_is_shown_a_link_it_cannot_open(string $role): void
    {
        $this->actingAs($this->userWithRole($role));

        $deadLinks = [];

        foreach ($this->navigableClasses() as $class) {
            try {
                if ($class::shouldRegisterNavigation() && ! $class::canAccess()) {
                    $deadLinks[] = class_basename($class);
                }
            } catch (\Throwable) {
                // A class that cannot answer either question is not a link.
            }
        }

        $this->assertSame(
            [],
            $deadLinks,
            "Sidebar links that 403 for '{$role}': " . implode(', ', $deadLinks),
        );
    }

    #[DataProvider('roles')]
    public function test_an_accessible_page_is_either_linked_or_deliberately_not(string $role): void
    {
        $this->actingAs($this->userWithRole($role));

        $unreachable = [];

        foreach ($this->navigableClasses() as $class) {
            if (in_array($class, self::INTENTIONALLY_UNLISTED, true)) {
                continue;
            }

            try {
                if (! $class::canAccess() || $class::shouldRegisterNavigation()) {
                    continue;
                }
            } catch (\Throwable) {
                continue;
            }

            // Openable, not hidden, no link — unless the page opts out of the
            // sidebar on purpose, which the Roles page tags as "no sidebar
            // link" so the setting does not read as broken.
            if (! \App\Filament\Resources\RoleResource::neverAppearsInSidebar($class)) {
                $unreachable[] = class_basename($class);
            }
        }

        $this->assertSame(
            [],
            $unreachable,
            "Openable by '{$role}' but no sidebar link and no opt-out: " . implode(', ', $unreachable),
        );
    }

    public function test_a_streamer_gets_the_locations_link_its_access_rule_grants(): void
    {
        $this->actingAs($this->userWithRole('streamer'));

        // passesModuleAccessCheck() admits streamers here on purpose — their
        // own locations are scoped in the query. The nav override used to say
        // admin/owner and quietly undo that.
        $this->assertTrue(\App\Filament\Resources\InventoryLocationResource::canAccess());
        $this->assertTrue(\App\Filament\Resources\InventoryLocationResource::shouldRegisterNavigation());
    }
}
