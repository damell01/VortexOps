<?php

namespace Tests\Feature\Admin;

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
        foreach (['admin', 'super_admin', 'streamer'] as $r) {
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

    public function test_non_owner_can_still_manage_non_privileged_roles(): void
    {
        $target = User::factory()->create(['email' => 'target3@test.com']);
        Livewire::actingAs($this->admin());

        Livewire::test(EditUser::class, ['record' => $target->getRouteKey()])
            ->fillForm(['roles' => [$this->roleId['streamer']]])
            ->call('save');

        $this->assertTrue($target->fresh()->hasRole('streamer'), 'a non-owner may assign non-privileged roles');
    }
}
