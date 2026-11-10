<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\ViewUser;
use App\Models\User;
use App\Notifications\AccountInviteNotification;
use App\Support\AccountInvite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/** New accounts get an email invite to set their own password; a typed temporary password must be changed first. */
class AccountInviteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['email' => 'dbellcreations@gmail.com', 'name' => 'Owner Person']));
    }

    public function test_creating_a_user_without_a_password_emails_an_invite_they_use_once(): void
    {
        Notification::fake();
        Livewire::test(CreateUser::class)
            ->fillForm(['name' => 'Crystal Rice', 'email' => 'crystal@example.test'])
            ->call('create')->assertHasNoFormErrors();
        $user = User::where('email', 'crystal@example.test')->firstOrFail();
        $this->assertFalse($user->must_change_password);

        $url = null;
        Notification::assertSentTo($user, AccountInviteNotification::class, function ($n) use (&$url, $user) {
            $url = $n->url;
            $mail = $n->toMail($user);
            return $mail->actionText === 'Set your password' && str_contains(implode(' ', $mail->introLines), 'Owner Person created');
        });

        auth()->logout();
        $this->get($url)->assertOk()->assertSee('Welcome to VortexOps')->assertSee('crystal@example.test');
        // A tampered link is refused.
        $this->get($url . 'x')->assertForbidden();
        $this->post($url, ['password' => 'short', 'password_confirmation' => 'short'])->assertSessionHasErrors('password');
        $this->post($url, ['password' => 'my-own-pass-1', 'password_confirmation' => 'my-own-pass-1'])->assertRedirect('/admin');
        $this->assertAuthenticatedAs($user->fresh());
        $this->assertTrue(Hash::check('my-own-pass-1', $user->fresh()->password));

        // Used once: the same link no longer works.
        auth()->logout();
        $this->get($url)->assertStatus(410)->assertSee('This invite has been used');
        $this->post($url, ['password' => 'another-pass-2', 'password_confirmation' => 'another-pass-2'])->assertStatus(410);
    }

    public function test_a_temporary_password_must_be_changed_at_first_sign_in(): void
    {
        Notification::fake();
        Livewire::test(CreateUser::class)
            ->fillForm(['name' => 'Temp Person', 'email' => 'temp@example.test', 'password' => 'temporary-123', 'send_invite' => false])
            ->call('create')->assertHasNoFormErrors();
        $user = User::where('email', 'temp@example.test')->firstOrFail();
        $this->assertTrue($user->must_change_password);
        $this->assertTrue(Hash::check('temporary-123', $user->password));
        Notification::assertNothingSentTo($user);

        $this->actingAs($user)->get('/admin')->assertRedirect(route('account.password.edit'));
    }

    public function test_an_admin_can_send_a_fresh_invite_and_a_failed_email_still_gives_the_link(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'later@example.test']);
        Livewire::test(ViewUser::class, ['record' => $user->getRouteKey()])->callAction('send_invite')->assertNotified('Invite sent to later@example.test');
        Notification::assertSentTo($user, AccountInviteNotification::class);

        $this->assertTrue(AccountInvite::current($user, \Illuminate\Support\Str::of(AccountInvite::url($user))->after('v=')->before('&')->toString()));
    }
}
