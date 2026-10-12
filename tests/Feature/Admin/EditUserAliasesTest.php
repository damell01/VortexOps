<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Saving a streamer whose profile had learned a name from a show title used
 * to fail after the user was already saved: the form lists every alias, and
 * the save recreated them all, colliding with the learned one.
 */
class EditUserAliasesTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_streamer_with_a_learned_alias_saves_cleanly_and_keeps_it(): void
    {
        config(['app.owner_email' => 'owner@test.com']);
        $streamerRole = Role::firstOrCreate(['name' => 'streamer', 'guard_name' => 'web']);
        $target = User::factory()->create(['name' => 'Micha', 'email' => 'micha@test.com']);
        $target->assignRole('streamer');
        $profile = $target->streamer()->create(['name' => 'Micha', 'status' => 'active', 'member_type' => 'streamer', 'payout_type' => 'profit_share']);
        $profile->aliases()->create(['alias' => 'Micha B', 'source' => 'show_title']);
        $profile->aliases()->create(['alias' => 'Old Name', 'source' => 'manual']);

        Livewire::actingAs(User::factory()->create(['email' => 'owner@test.com']));

        foreach (['Micha Bell', 'Micha Bell Jr'] as $name) {
            Livewire::test(EditUser::class, ['record' => $target->getRouteKey()])
                ->assertFormSet(['streamer_aliases' => ['Micha B', 'Old Name']])
                ->fillForm(['name' => $name, 'roles' => [$streamerRole->id]])
                ->call('save')
                ->assertHasNoFormErrors();
            $target->refresh();
            $this->assertSame($name, $target->name);
            $this->assertSame($name, $profile->fresh()->name);
        }

        // Removing one from the list drops it; adding one keeps the learned one.
        Livewire::test(EditUser::class, ['record' => $target->getRouteKey()])
            ->fillForm(['roles' => [$streamerRole->id], 'streamer_aliases' => ['Micha B', 'Mb']])
            ->call('save')
            ->assertHasNoFormErrors();

        $aliases = $profile->aliases()->pluck('source', 'alias')->all();
        $this->assertSame(['Mb' => 'manual', 'Micha B' => 'show_title'], collect($aliases)->sortKeys()->all());
    }
}
