<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\LedgerResource;
use App\Models\User;
use App\Support\NavVisibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Per-role page visibility: NavVisibility::isHiddenForUser() hides a page only
 * when EVERY one of the user's roles hides it; the owner is always immune.
 */
class NavVisibilityByRoleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        NavVisibility::flushMemo();
        config(['app.owner_email' => 'dbellcreations@gmail.com']);

        foreach (['manager', 'lead', 'admin'] as $name) {
            Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
    }

    private function userWithRoles(array $roles, string $email = 'user@test.com'): User
    {
        $u = User::factory()->create(['email' => $email]);
        $u->assignRole($roles);
        return $u;
    }

    public function test_page_hidden_when_the_users_only_role_hides_it(): void
    {
        NavVisibility::setHiddenForRole('manager', [LedgerResource::class]);
        $user = $this->userWithRoles(['manager']);

        $this->assertTrue(NavVisibility::isHiddenForUser(LedgerResource::class, $user));
    }

    public function test_page_visible_when_another_role_grants_it(): void
    {
        // manager hides Ledger, but the same user is also a lead, who does not.
        NavVisibility::setHiddenForRole('manager', [LedgerResource::class]);
        $user = $this->userWithRoles(['manager', 'lead']);

        $this->assertFalse(NavVisibility::isHiddenForUser(LedgerResource::class, $user));
    }

    public function test_page_hidden_only_when_all_roles_hide_it(): void
    {
        NavVisibility::setHiddenForRole('manager', [LedgerResource::class]);
        NavVisibility::setHiddenForRole('lead', [LedgerResource::class]);
        $user = $this->userWithRoles(['manager', 'lead']);

        $this->assertTrue(NavVisibility::isHiddenForUser(LedgerResource::class, $user));
    }

    public function test_owner_is_always_immune(): void
    {
        NavVisibility::setHiddenForRole('manager', [LedgerResource::class]);
        $owner = $this->userWithRoles(['manager'], 'dbellcreations@gmail.com');

        $this->assertFalse(NavVisibility::isHiddenForUser(LedgerResource::class, $owner));
    }

    public function test_user_with_no_roles_sees_everything(): void
    {
        NavVisibility::setHiddenForRole('manager', [LedgerResource::class]);
        $user = User::factory()->create(['email' => 'noroles@test.com']);

        $this->assertFalse(NavVisibility::isHiddenForUser(LedgerResource::class, $user));
    }

    public function test_nav_items_empty_when_hidden_for_the_users_role(): void
    {
        NavVisibility::setHiddenForRole('manager', [LedgerResource::class]);
        $user = $this->userWithRoles(['manager']);
        Auth::login($user);

        $this->assertEmpty(LedgerResource::getNavigationItems());
    }

    public function test_nav_items_present_when_a_second_role_grants_it(): void
    {
        NavVisibility::setHiddenForRole('manager', [LedgerResource::class]);
        $user = $this->userWithRoles(['manager', 'lead']);
        Auth::login($user);

        $this->assertNotEmpty(LedgerResource::getNavigationItems());
    }
}
