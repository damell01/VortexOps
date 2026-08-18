<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\RoleResource;
use App\Models\User;
use App\Support\NavVisibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * A role sees the pages ticked Visible on Roles & Permissions, and nothing
 * else.
 *
 * It used to be stored the other way round — a list of what each role could
 * NOT see — which grants everything absent from that list. The two are only
 * equivalent at the moment the role is saved: every page shipped afterwards
 * was granted to every role automatically, so the sidebar drifted further from
 * the Roles screen with each release.
 */
class AllowListVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private const GRANTED = \App\Filament\Resources\InventoryItemResource::class;
    private const DENIED  = \App\Filament\Pages\InventoryAge::class;

    /** Stands in for a page that ships after the role was last saved. */
    private const SHIPPED_LATER = \App\Filament\Pages\QuickAddContainerScan::class;

    private function roleUser(string $role = 'reviewer'): User
    {
        Role::findOrCreate($role, 'web');

        $user = User::factory()->create(['email' => "{$role}-allow@example.test"]);
        $user->assignRole($role);

        filament()->setCurrentPanel(filament()->getPanel('admin'));

        return $user->fresh();
    }

    private function sees(User $user, string $class): bool
    {
        return ! NavVisibility::isHiddenForUser($class, $user);
    }

    public function test_a_role_sees_only_what_is_ticked(): void
    {
        $user = $this->roleUser();

        NavVisibility::setVisibleForRole('reviewer', [self::GRANTED]);
        NavVisibility::flushMemo();

        $this->assertTrue($this->sees($user, self::GRANTED));
        $this->assertFalse($this->sees($user, self::DENIED));

        $visible = array_filter(
            RoleResource::roleControlledPages(),
            fn (string $class): bool => $this->sees($user, $class),
        );

        $this->assertSame(
            [self::GRANTED],
            array_values($visible),
            'The role should see exactly the one page it was granted.',
        );
    }

    public function test_a_page_that_ships_later_is_not_granted_automatically(): void
    {
        $user = $this->roleUser();

        // Saved without ever naming this page — under a hide-list that is
        // indistinguishable from "allowed", which is the whole bug.
        NavVisibility::setVisibleForRole('reviewer', [self::GRANTED]);
        NavVisibility::flushMemo();

        $this->assertFalse(
            $this->sees($user, self::SHIPPED_LATER),
            'A page nobody ticked must not appear just because it is new.',
        );
    }

    public function test_an_unconfigured_role_is_not_locked_out(): void
    {
        $user = $this->roleUser('brand-new');

        NavVisibility::flushMemo();

        // No allow-list has ever been written for this role. Denying everything
        // would black out the panel for an install that has not visited the
        // Roles screen yet.
        $this->assertTrue($this->sees($user, self::GRANTED));
        $this->assertTrue($this->sees($user, self::DENIED));
    }

    public function test_the_most_permissive_role_wins(): void
    {
        $user = $this->roleUser('reviewer');
        Role::findOrCreate('auditor', 'web');
        $user->assignRole('auditor');
        $user = $user->fresh();

        NavVisibility::setVisibleForRole('reviewer', [self::GRANTED]);
        NavVisibility::setVisibleForRole('auditor', [self::DENIED]);
        NavVisibility::flushMemo();

        // Each role grants one page; holding both should grant both.
        $this->assertTrue($this->sees($user, self::GRANTED));
        $this->assertTrue($this->sees($user, self::DENIED));
    }

    public function test_the_owner_is_never_filtered(): void
    {
        $owner = User::factory()->create(['email' => config('app.owner_email')]);
        Role::findOrCreate('reviewer', 'web');
        $owner->assignRole('reviewer');
        $owner = $owner->fresh();

        NavVisibility::setVisibleForRole('reviewer', []);
        NavVisibility::flushMemo();

        $this->assertTrue($this->sees($owner, self::GRANTED));
    }

    public function test_saving_the_roles_form_stores_what_was_ticked(): void
    {
        $this->roleUser();

        $perms = [];

        foreach (RoleResource::roleControlledPages() as $class) {
            $perms[RoleResource::pageKey($class)] = [
                'visible'  => $class === self::GRANTED,
                'editable' => true,
            ];
        }

        [$hidden, $readonly, $visible] = RoleResource::pagePermsToLists($perms);

        $this->assertSame([self::GRANTED], $visible);
        $this->assertNotContains(self::GRANTED, $hidden);
        $this->assertContains(self::DENIED, $hidden);
    }

    public function test_the_form_reflects_the_stored_allow_list(): void
    {
        $this->roleUser();

        NavVisibility::setVisibleForRole('reviewer', [self::GRANTED]);
        NavVisibility::flushMemo();

        $state = RoleResource::pagePermsFormState('reviewer', []);

        $this->assertTrue($state[RoleResource::pageKey(self::GRANTED)]['visible']);
        $this->assertFalse($state[RoleResource::pageKey(self::DENIED)]['visible']);

        // A page absent from the allow-list must show as unticked, not ticked
        // — otherwise the screen claims access the role does not have.
        $this->assertFalse($state[RoleResource::pageKey(self::SHIPPED_LATER)]['visible']);
    }
}
