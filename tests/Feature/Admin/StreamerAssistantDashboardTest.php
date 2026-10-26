<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\DashboardImproved;
use App\Filament\Pages\Timekeeping;
use App\Filament\Resources\ShowResource;
use App\Filament\Resources\ShowResource\Pages\ListShows;
use App\Filament\Resources\ShowResource\Pages\ViewShow;
use App\Models\Show;
use App\Models\User;
use App\Models\WhatnotChannel;
use App\Support\ChannelContext;
use App\Support\DashboardShortcuts;
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
        $this->enableAdminModules();
        Role::findOrCreate('streamer_assistant', 'web');
        $user = User::factory()->create();
        $user->assignRole('streamer_assistant');
        $this->actingAs($user);
        return $user;
    }

    public function test_assistant_home_and_show_pages_work_with_an_older_saved_role_list(): void
    {
        $user = $this->assistant();
        NavVisibility::setVisibleForRole('streamer_assistant', []);
        $channel = WhatnotChannel::create(['name' => 'Other channel', 'status' => 'active']);
        $otherChannel = WhatnotChannel::create(['name' => 'Selected channel', 'status' => 'active']);
        ChannelContext::setActive($otherChannel->id);
        $upcoming = Show::create(['title' => 'Another host future show', 'show_date' => today()->addDay(), 'status' => 'draft', 'whatnot_channel_id' => $channel->id]);
        $past = Show::create(['title' => 'Another host past show', 'show_date' => today()->subDay(), 'status' => 'closed', 'is_operational' => false, 'whatnot_channel_id' => $otherChannel->id]);

        $nav = view('filament.components.desktop-mega-nav')->render();
        $this->assertStringContainsString('All Shows', $nav);
        $this->assertStringContainsString('My Pay', $nav);
        $this->assertTrue(ShowResource::canAccess());
        $this->assertTrue(ShowResource::canViewAny());
        $this->assertTrue(ShowResource::canView($upcoming));
        $this->assertTrue(Timekeeping::canAccess());
        $this->assertEqualsCanonicalizing([$upcoming->id, $past->id], ShowResource::getEloquentQuery()->pluck('id')->all());
        $this->assertContains('All shows', array_column(DashboardShortcuts::forCurrentUser(), 'label'));
        $this->assertNotContains('Receive inventory', array_column(DashboardShortcuts::forCurrentUser(), 'label'));
        $this->assertSame('assistant', (new DashboardImproved)->getViewData()['roleMode']);
        $this->assertSame([], (new DashboardImproved)->getWidgets());
        Livewire::test(DashboardImproved::class)->assertSuccessful()->assertSee('Another host future show')->assertSee('Another host past show')->assertDontSee('Receive inventory');
        Livewire::test(ListShows::class)->loadTable()->assertSuccessful()->assertCanSeeTableRecords([$upcoming, $past]);
        Livewire::test(ViewShow::class, ['record' => $upcoming->id])->assertSuccessful()->assertSee('Other channel')->assertDontSee('Show Financials')->assertDontSee('Edit Show');
        $this->assertFalse(ShowResource::canCreate());
        $this->assertFalse(ShowResource::canEdit($upcoming));
        $this->assertFalse(ShowResource::canDelete($upcoming));
        $this->assertFalse($user->can('update', $upcoming));
    }

    public function test_explicit_hiding_and_disabled_modules_are_honored_on_the_dashboard(): void
    {
        $this->assistant();
        NavVisibility::setHiddenForRole('streamer_assistant', [ShowResource::class]);
        $this->assertFalse(ShowResource::canAccess());
        $this->assertEmpty((new DashboardImproved)->getViewData()['upcomingAssistantShows']);
        Livewire::test(DashboardImproved::class)->assertSuccessful()->assertDontSee('View all shows');
        Livewire::test(ListShows::class)->assertForbidden();
        NavVisibility::setHiddenForRole('streamer_assistant', []);
        $this->enableAdminModules([]);
        $this->assertFalse(ShowResource::canAccess());
        $this->assertEmpty((new DashboardImproved)->getViewData()['recentAssistantShows']);
    }

    public function test_assistant_role_wins_over_streamer_scoping_without_becoming_an_admin(): void
    {
        $user = $this->assistant();
        Role::findOrCreate('streamer', 'web');
        $user->assignRole('streamer');
        $show = Show::create(['title' => 'Unassigned show', 'show_date' => today(), 'status' => 'draft']);
        $this->assertFalse($user->isAdmin());
        $this->assertTrue(ShowResource::getEloquentQuery()->whereKey($show)->exists());
        $this->assertSame('assistant', (new DashboardImproved)->getViewData()['roleMode']);
        $this->assertSame([], (new DashboardImproved)->getWidgets());
        $this->assertFalse(ShowResource::canEdit($show));
    }
    public function test_assistants_see_the_leaderboard_and_only_their_paid_pay_runs(): void
    {
        $user = $this->assistant();
        NavVisibility::setVisibleForRole('streamer_assistant', []);
        $person = \App\Models\Streamer::create(['name' => $user->name, 'user_id' => $user->id, 'member_type' => 'assistant', 'status' => 'active']);
        $other = \App\Models\Streamer::create(['name' => 'Other assistant', 'status' => 'active']);
        $paid = \App\Models\WeeklyPayoutBatch::create(['week_start' => '2026-10-12', 'week_end' => '2026-10-18', 'status' => 'paid']);
        $draft = \App\Models\WeeklyPayoutBatch::create(['week_start' => '2026-10-19', 'week_end' => '2026-10-25', 'status' => 'draft']);
        $mine = \App\Models\Payout::create(['streamer_id' => $person->id, 'weekly_payout_batch_id' => $paid->id, 'status' => 'paid', 'payout_type' => 'hourly', 'calculated_payout' => 125.25]);
        \App\Models\Payout::create(['streamer_id' => $other->id, 'weekly_payout_batch_id' => $paid->id, 'status' => 'paid', 'payout_type' => 'hourly', 'calculated_payout' => 987.65]);
        \App\Models\Payout::create(['streamer_id' => $person->id, 'weekly_payout_batch_id' => $draft->id, 'status' => 'draft', 'payout_type' => 'hourly', 'calculated_payout' => 876.54]);
        $this->assertSame([$mine->id], \App\Support\AssistantPaidPay::query($user)->pluck('id')->all());
        $this->assertTrue(\App\Livewire\StreamerLeaderboard::canView());
        $this->assertTrue(\App\Filament\Pages\ShowLeaderboard::canAccess());
        Livewire::test(\App\Filament\Pages\MyPay::class)->assertSuccessful()->assertSee('125.25')->assertDontSee('987.65')->assertDontSee('876.54');
        Livewire::test(DashboardImproved::class)->assertSuccessful()->assertSee('Payment history')->assertSee('125.25')->assertDontSee('987.65')->assertDontSee('876.54');
        Livewire::test(\App\Livewire\StreamerLeaderboard::class)->assertSuccessful();
        $unlinked = User::factory()->create();
        $unlinked->assignRole('streamer_assistant');
        $this->assertSame(0, \App\Support\AssistantPaidPay::query($unlinked)->count());
        NavVisibility::setHiddenForRole('streamer_assistant', [\App\Filament\Pages\MyPay::class, \App\Filament\Pages\ShowLeaderboard::class]);
        $this->assertFalse(\App\Filament\Pages\MyPay::canAccess());
        $this->assertFalse(\App\Livewire\StreamerLeaderboard::canView());
    }

    public function test_rollout_enables_requested_pages_on_existing_saved_assistant_roles(): void
    {
        $this->assistant();
        NavVisibility::setVisibleForRole('streamer_assistant', [\App\Filament\Resources\PalletResource::class]);
        NavVisibility::setHiddenForRole('streamer_assistant', [ShowResource::class, Timekeeping::class, \App\Filament\Pages\MyPay::class, \App\Filament\Pages\ShowLeaderboard::class, \App\Filament\Pages\PayrollOverview::class]);
        NavVisibility::setVisibleForRole('streamer', []);
        $migration = require database_path('migrations/2026_10_26_140000_enable_streamer_assistant_workspace.php');
        $migration->up();
        $this->assertTrue(ShowResource::canAccess());
        $this->assertTrue(Timekeeping::canAccess());
        $this->assertTrue(\App\Filament\Pages\MyPay::canAccess());
        $this->assertTrue(\App\Filament\Pages\ShowLeaderboard::canAccess());
        $this->assertContains(\App\Filament\Resources\PalletResource::class, NavVisibility::visibleForRole('streamer_assistant'));
        $this->assertContains(\App\Filament\Pages\PayrollOverview::class, NavVisibility::hiddenForRole('streamer_assistant'));
        $this->assertSame([], NavVisibility::visibleForRole('streamer'));
    }

}
