<?php
namespace Tests\Feature\Admin;

use App\Filament\Pages\{DashboardImproved, MyPay, ShowLeaderboard, Shows, Timekeeping};
use App\Filament\Resources\{InventoryItemResource, PalletResource, ShowResource, WeeklyPayoutBatchResource};
use App\Models\{Payout, Show, Streamer, User, WeeklyPayoutBatch};
use App\Support\NavVisibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StreamerAssistantDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function assistant(): User
    {
        $this->withoutVite();
        $this->enableAdminModules();
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('streamer_assistant', 'web'));
        $this->actingAs($user);
        return $user;
    }

    public function test_dashboard_only_has_the_clock_and_own_paid_pay_runs(): void
    {
        $user = $this->assistant();
        Show::create(['title' => 'Confidential team show', 'show_date' => today(), 'status' => 'draft']);
        $person = Streamer::create(['name' => 'Assistant', 'user_id' => $user->id, 'member_type' => 'assistant', 'status' => 'active']);
        $other = Streamer::create(['name' => 'Other person', 'status' => 'active']);
        $paid = WeeklyPayoutBatch::create(['week_start' => '2026-10-12', 'week_end' => '2026-10-18', 'status' => 'paid']);
        $draft = WeeklyPayoutBatch::create(['week_start' => '2026-10-19', 'week_end' => '2026-10-25', 'status' => 'draft']);
        Payout::create(['streamer_id' => $person->id, 'weekly_payout_batch_id' => $paid->id, 'status' => 'paid', 'payout_type' => 'hourly', 'calculated_payout' => 125.25]);
        Payout::create(['streamer_id' => $other->id, 'weekly_payout_batch_id' => $paid->id, 'status' => 'paid', 'payout_type' => 'hourly', 'calculated_payout' => 987.65]);
        Payout::create(['streamer_id' => $person->id, 'weekly_payout_batch_id' => $draft->id, 'status' => 'draft', 'payout_type' => 'hourly', 'calculated_payout' => 876.54]);
        Livewire::test(DashboardImproved::class)->assertSuccessful()->assertSee('Clock in')->assertSee('Paid pay runs')
            ->assertSee('125.25')->assertDontSee('987.65')->assertDontSee('876.54')
            ->assertDontSee('Confidential team show')->assertDontSee('Upcoming shows')->assertDontSee('leaderboard')
            ->assertDontSee('Receive inventory')->assertDontSee('Choose a show');
        $this->assertSame([], (new DashboardImproved)->getWidgets());
        $this->get(DashboardImproved::getUrl())->assertOk();
    }

    public function test_saved_grants_cannot_reopen_other_pages_or_direct_urls(): void
    {
        $user = $this->assistant();
        $pages = [Shows::class, ShowLeaderboard::class, Timekeeping::class, MyPay::class, InventoryItemResource::class, ShowResource::class, PalletResource::class, WeeklyPayoutBatchResource::class];
        NavVisibility::setVisibleForRole('streamer_assistant', $pages);
        foreach ($pages as $class) {
            $this->assertTrue(NavVisibility::isHiddenForUser($class, $user), $class);
            $this->get($class::getUrl())->assertForbidden();
        }
        $menu = view('filament.components.desktop-mega-nav')->render();
        foreach (['Show Calendar', 'Show Leaderboard', 'All Inventory', 'Timekeeping', 'My notifications', 'Help & Guides'] as $label) {
            $this->assertStringNotContainsString($label, $menu);
        }
        $this->assertFalse(\App\Livewire\StreamerLeaderboard::canView());
    }

    public function test_assistant_role_wins_over_old_admin_and_streamer_grants(): void
    {
        $user = $this->assistant();
        foreach (['admin', 'streamer'] as $role) $user->assignRole(Role::findOrCreate($role, 'web'));
        $this->assertTrue($user->isStreamerAssistant());
        $this->assertFalse($user->isAdmin());
        $this->assertFalse($user->canSwitchChannels());
        $this->get(ShowResource::getUrl('index'))->assertForbidden();
        $this->get(DashboardImproved::getUrl())->assertOk();
    }

    public function test_owner_access_is_unchanged(): void
    {
        $this->enableAdminModules();
        $owner = User::factory()->create(['email' => config('app.owner_email') ?: 'dbellcreations@gmail.com']);
        $owner->assignRole(Role::findOrCreate('streamer_assistant', 'web'));
        $this->actingAs($owner);
        $this->assertTrue($owner->isAdmin());
        $this->assertFalse($owner->isStreamerAssistant());
        $this->assertFalse(NavVisibility::isHiddenForUser(ShowResource::class, $owner));
    }

    public function test_unlinked_assistant_has_no_access_to_anyone_elses_pay(): void
    {
        $this->assistant();
        Livewire::test(DashboardImproved::class)->assertSuccessful()->assertSee('No paid pay runs yet.');
    }
}
