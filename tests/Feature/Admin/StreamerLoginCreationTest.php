<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\StreamerResource\Pages\CreateStreamer;
use App\Models\Streamer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The streamer create form can optionally mint a login (User account with the
 * streamer role) linked to the new streamer profile in one step.
 */
class StreamerLoginCreationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $u = User::factory()->create();
        $u->assignRole('admin');
        return $u;
    }

    public function test_creating_a_streamer_with_a_login_creates_a_linked_streamer_user(): void
    {
        Livewire::actingAs($this->admin());

        Livewire::test(CreateStreamer::class)
            ->fillForm([
                'name'           => 'Jay VCX',
                'payout_type'    => 'hourly',
                'create_login'   => true,
                'login_email'    => 'jay@vortex.test',
                'login_password' => 'secret-pass-1',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $user = User::where('email', 'jay@vortex.test')->first();
        $this->assertNotNull($user, 'a User should be created for the login email');
        $this->assertTrue($user->hasRole('streamer'));
        $this->assertTrue(Hash::check('secret-pass-1', $user->password));

        $streamer = Streamer::where('name', 'Jay VCX')->first();
        $this->assertNotNull($streamer);
        $this->assertEquals($user->id, $streamer->user_id, 'the streamer profile should link back to the new user');
    }

    public function test_creating_a_streamer_without_a_login_creates_no_user(): void
    {
        Livewire::actingAs($this->admin());

        Livewire::test(CreateStreamer::class)
            ->fillForm([
                'name'        => 'No Login Streamer',
                'payout_type' => 'hourly',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertNotNull(Streamer::where('name', 'No Login Streamer')->first());
        $this->assertEquals(1, User::count(), 'only the acting admin should exist');
    }

    public function test_login_email_must_not_collide_with_an_existing_user(): void
    {
        User::factory()->create(['email' => 'taken@vortex.test']);
        Livewire::actingAs($this->admin());

        Livewire::test(CreateStreamer::class)
            ->fillForm([
                'name'           => 'Dup Email Streamer',
                'payout_type'    => 'hourly',
                'create_login'   => true,
                'login_email'    => 'taken@vortex.test',
                'login_password' => 'secret-pass-1',
            ])
            ->call('create')
            ->assertHasFormErrors(['login_email']);

        $this->assertNull(Streamer::where('name', 'Dup Email Streamer')->first(), 'creation should be blocked by the duplicate email');
    }
}
