<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Only the owner may grant or revoke privileged roles (admin / super_admin);
 * other admins can manage non-privileged roles but can neither escalate an
 * account nor demote a super admin.
 */
class RoleEscalationTest extends TestCase
{
    use RefreshDatabase;

    private array $roleId = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.owner_email' => 'owner@test.com']);
        foreach (['admin', 'super_admin', 'streamer', 'fulfillment_admin'] as $r) {
            $this->roleId[$r] = Role::firstOrCreate(['name' => $r, 'guard_name' => 'web'])->id;
        }
    }

    private function owner(): User
    {
        return User::factory()->create(['email' => 'owner@test.com']);
    }

    private function admin(): User
    {
        $u = User::factory()->create(['email' => 'admin@test.com']);
        $u->assignRole('admin');
        return $u;
    }

    public function test_non_owner_cannot_escalate_a_user_to_admin(): void
    {
        $target = User::factory()->create(['email' => 'target@test.com']);
        Livewire::actingAs($this->admin());

        Livewire::test(EditUser::class, ['record' => $target->getRouteKey()])
            ->fillForm(['roles' => [$this->roleId['admin']]])
            ->call('save');

        $this->assertFalse($target->fresh()->hasRole('admin'), 'admin must not be grantable by a non-owner');
    }

    public function test_non_owner_cannot_demote_a_super_admin(): void
    {
        $target = User::factory()->create(['email' => 'super@test.com']);
        $target->assignRole('super_admin');

        Livewire::actingAs($this->admin());

        // Try to strip super_admin by submitting only a non-privileged role.
        Livewire::test(EditUser::class, ['record' => $target->getRouteKey()])
            ->fillForm(['roles' => [$this->roleId['streamer']]])
            ->call('save');

        $this->assertTrue($target->fresh()->hasRole('super_admin'), 'a non-owner must not be able to remove super_admin');
    }

    public function test_owner_can_grant_a_privileged_role(): void
    {
        $target = User::factory()->create(['email' => 'target2@test.com']);
        Livewire::actingAs($this->owner());

        Livewire::test(EditUser::class, ['record' => $target->getRouteKey()])
            ->fillForm(['roles' => [$this->roleId['admin']]])
            ->call('save');

        $this->assertTrue($target->fresh()->hasRole('admin'), 'the owner may grant privileged roles');
    }

    public function test_non_owner_cannot_escalate_a_user_to_fulfillment_admin(): void
    {
        $target = User::factory()->create(['email' => 'target4@test.com']);
        Livewire::actingAs($this->admin());

        Livewire::test(EditUser::class, ['record' => $target->getRouteKey()])
            ->fillForm(['roles' => [$this->roleId['fulfillment_admin']]])
            ->call('save');

        $this->assertFalse($target->fresh()->hasRole('fulfillment_admin'), 'fulfillment_admin must not be grantable by a non-owner');
    }

    public function test_non_owner_can_still_manage_non_privileged_roles(): void
    {
        $target = User::factory()->create(['email' => 'target3@test.com']);
        Livewire::actingAs($this->admin());

        Livewire::test(EditUser::class, ['record' => $target->getRouteKey()])
            ->fillForm(['roles' => [$this->roleId['streamer']]])
            ->call('save');

        $this->assertTrue($target->fresh()->hasRole('streamer'), 'a non-owner may assign non-privileged roles');
    }

    // ── super_admin is frozen: nobody, owner included, may grant or revoke it ──

    public function test_not_even_the_owner_can_grant_super_admin_on_edit(): void
    {
        $target = User::factory()->create(['email' => 'target5@test.com']);
        Livewire::actingAs($this->owner());

        Livewire::test(EditUser::class, ['record' => $target->getRouteKey()])
            ->fillForm(['roles' => [$this->roleId['super_admin']]])
            ->call('save');

        $this->assertFalse($target->fresh()->hasRole('super_admin'), 'super_admin must not be grantable by anyone, owner included');
    }

    public function test_not_even_the_owner_can_create_a_user_with_super_admin(): void
    {
        Livewire::actingAs($this->owner());

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name'     => 'Sneaky Super',
                'email'    => 'sneaky@test.com',
                'password' => 'password123',
                'roles'    => [$this->roleId['super_admin']],
            ])
            ->call('create');

        // Filament rejects the filtered-out option at validation, so creation is
        // blocked outright; if that layer ever changed, afterCreate strips the
        // role. Either way: no new super_admin may come into existence.
        $created = User::where('email', 'sneaky@test.com')->first();
        if ($created !== null) {
            $this->assertFalse($created->hasRole('super_admin'), 'a freshly created user must never come out holding super_admin');
        }
        $this->assertEquals(
            0,
            User::role('super_admin')->where('email', 'sneaky@test.com')->count(),
            'no new super_admin account may be minted'
        );
    }

    public function test_an_existing_super_admin_survives_an_unrelated_owner_edit(): void
    {
        $target = User::factory()->create(['email' => 'existing-super@test.com']);
        $target->assignRole('super_admin');

        Livewire::actingAs($this->owner());

        // super_admin is filtered out of the select options, so the submitted
        // state won't include it — the role must be restored, not silently lost.
        Livewire::test(EditUser::class, ['record' => $target->getRouteKey()])
            ->fillForm(['roles' => [$this->roleId['streamer']]])
            ->call('save');

        $this->assertTrue($target->fresh()->hasRole('super_admin'), 'an unrelated edit must not demote an existing super admin');
    }
}
