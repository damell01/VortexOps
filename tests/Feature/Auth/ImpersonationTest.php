<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Support\Impersonation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImpersonationTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->create(['email' => 'dbellcreations@gmail.com', 'name' => 'Owner Person']);
    }

    public function test_only_the_owner_can_log_in_as_someone_else(): void
    {
        $owner = $this->owner();
        $other = User::factory()->create();
        $third = User::factory()->create();

        $this->assertTrue(Impersonation::canImpersonate($owner, $other));
        $this->assertFalse(Impersonation::canImpersonate($owner, $owner));
        $this->assertFalse(Impersonation::canImpersonate($other, $third));
        $this->assertFalse(Impersonation::canImpersonate(null, $other));
    }

    public function test_owner_logs_in_as_user_sees_banner_and_returns(): void
    {
        $owner = $this->owner();
        $target = User::factory()->create(['name' => 'Jordan Demo', 'must_change_password' => true]);

        $this->actingAs($owner);
        Impersonation::start($owner, $target);

        $this->assertSame($target->id, auth()->id());
        $this->assertTrue(Impersonation::active());
        $this->assertSame($owner->id, Impersonation::impersonator()->id);

        // A pending password change on their account doesn't trap the owner.
        $page = $this->get('/admin');
        $this->assertNotSame(route('account.password.edit'), $page->headers->get('Location'));

        $this->assertStringContainsString("You're logged in as Jordan Demo",
            view('filament.components.impersonation-banner')->render());

        $this->post(route('impersonation.stop'))->assertRedirect();
        $this->assertSame($owner->id, auth()->id());
        $this->assertFalse(Impersonation::active());
    }

    public function test_cannot_start_a_second_switch_while_logged_in_as_someone(): void
    {
        $owner = $this->owner();
        $a = User::factory()->create();
        $b = User::factory()->create();

        $this->actingAs($owner);
        Impersonation::start($owner, $a);

        $this->assertFalse(Impersonation::canImpersonate($owner, $b));
    }
}
