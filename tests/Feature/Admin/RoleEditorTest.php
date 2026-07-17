<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\LedgerResource;
use App\Filament\Resources\RoleResource;
use App\Filament\Resources\RoleResource\Pages\CreateRole;
use App\Filament\Resources\RoleResource\Pages\EditRole;
use App\Models\User;
use App\Support\NavVisibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The Role editor is owner-only and its "Page Visibility" checklist must persist
 * to the per-role nav-visibility setting on create and edit.
 */
class RoleEditorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        NavVisibility::flushMemo();
        config(['app.owner_email' => 'dbellcreations@gmail.com']);
    }

    private function owner(): User
    {
        return User::factory()->create(['email' => 'dbellcreations@gmail.com']);
    }

    private function nonOwner(): User
    {
        return User::factory()->create(['email' => 'someone@test.com']);
    }

    public function test_role_manager_is_owner_only(): void
    {
        $this->actingAs($this->nonOwner());
        $this->assertFalse(RoleResource::canAccess());
        $this->assertFalse(RoleResource::shouldRegisterNavigation());

        $this->actingAs($this->owner());
        $this->assertTrue(RoleResource::canAccess());
        $this->assertTrue(RoleResource::shouldRegisterNavigation());
    }

    public function test_create_role_persists_hidden_pages(): void
    {
        Livewire::actingAs($this->owner());

        Livewire::test(CreateRole::class)
            ->fillForm([
                'name'         => 'manager',
                'guard_name'   => 'web',
                'hidden_pages' => [LedgerResource::class],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('roles', ['name' => 'manager']);
        $this->assertEquals([LedgerResource::class], NavVisibility::hiddenForRole('manager'));
    }

    public function test_edit_role_loads_and_updates_hidden_pages(): void
    {
        $role = Role::create(['name' => 'manager', 'guard_name' => 'web']);
        NavVisibility::setHiddenForRole('manager', [LedgerResource::class]);

        Livewire::actingAs($this->owner());

        // The form pre-fills with the currently hidden pages.
        $component = Livewire::test(EditRole::class, ['record' => $role->getRouteKey()])
            ->assertFormSet(['hidden_pages' => [LedgerResource::class]]);

        // Clearing the checklist and saving removes the hidden page.
        $component
            ->fillForm(['hidden_pages' => []])
            ->call('save')
            ->assertHasNoFormErrors();

        NavVisibility::flushMemo();
        $this->assertEquals([], NavVisibility::hiddenForRole('manager'));
    }

    public function test_create_role_persists_readonly_pages(): void
    {
        Livewire::actingAs($this->owner());

        Livewire::test(CreateRole::class)
            ->fillForm([
                'name'           => 'manager',
                'guard_name'     => 'web',
                'readonly_pages' => [LedgerResource::class],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertEquals([LedgerResource::class], NavVisibility::readonlyForRole('manager'));
    }

    public function test_edit_role_loads_and_updates_readonly_pages(): void
    {
        $role = Role::create(['name' => 'manager', 'guard_name' => 'web']);
        NavVisibility::setReadonlyForRole('manager', [LedgerResource::class]);

        Livewire::actingAs($this->owner());

        $component = Livewire::test(EditRole::class, ['record' => $role->getRouteKey()])
            ->assertFormSet(['readonly_pages' => [LedgerResource::class]]);

        $component
            ->fillForm(['readonly_pages' => []])
            ->call('save')
            ->assertHasNoFormErrors();

        NavVisibility::flushMemo();
        $this->assertEquals([], NavVisibility::readonlyForRole('manager'));
    }

    public function test_core_roles_cannot_be_deleted(): void
    {
        Livewire::actingAs($this->owner());

        foreach (['admin', 'super_admin', 'streamer', 'fulfillment', 'fulfillment_admin'] as $name) {
            $role = Role::create(['name' => $name, 'guard_name' => 'web']);

            Livewire::test(EditRole::class, ['record' => $role->getRouteKey()])
                ->assertActionHidden('delete');
        }
    }

    public function test_custom_roles_can_be_deleted(): void
    {
        $role = Role::create(['name' => 'seasonal', 'guard_name' => 'web']);
        Livewire::actingAs($this->owner());

        Livewire::test(EditRole::class, ['record' => $role->getRouteKey()])
            ->assertActionVisible('delete');
    }
}
