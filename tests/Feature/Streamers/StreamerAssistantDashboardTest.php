<?php

namespace Tests\Feature\Streamers;

use App\Filament\Pages\DashboardImproved;
use App\Filament\Pages\ManagerProfitShare;
use App\Filament\Pages\ShowLeaderboard;
use App\Filament\Pages\StockTransfer;
use App\Filament\Pages\StreamerProfitShare;
use App\Filament\Pages\Timekeeping;
use App\Filament\Resources\InventoryItemResource;
use App\Filament\Resources\PalletResource;
use App\Filament\Resources\PayoutResource;
use App\Models\Payout;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\WeeklyPayoutBatch;
use App\Support\NavVisibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Streamer assistants fell through to a generic dashboard of receiving
 * shortcuts, could not open Timekeeping (though they are paid from clocked
 * hours) or the inventory list, and — with no page list saved for the role —
 * could open payouts, profit share and the other admin pages.
 */
class StreamerAssistantDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $assistant;

    private Streamer $streamer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableAdminModules();
        $this->assistant = User::factory()->create(['name' => 'Avery Helper']);
        $this->assistant->assignRole(Role::findOrCreate('streamer_assistant', 'web'));
        $this->streamer = Streamer::create(['name' => 'Jordan', 'status' => 'active', 'member_type' => 'streamer']);
        $this->assistant->assistedStreamers()->attach($this->streamer->id);
        $this->actingAs($this->assistant->fresh());
    }

    public function test_the_dashboard_shows_cards_a_calendar_link_and_the_clock(): void
    {
        $upcoming = Show::create(['title' => 'Prizm Pack Wars', 'show_date' => now()->addDays(2)->toDateString(), 'start_time' => '18:00:00', 'status' => 'mapping']);
        $upcoming->streamers()->attach($this->streamer->id);

        Livewire::test(DashboardImproved::class)->assertSuccessful()
            ->assertSee('Prizm Pack Wars')
            ->assertSee('vx-ah-card', false)
            ->assertSee(\App\Filament\Pages\Shows::getUrl(), false)
            ->assertSee('Clock in')
            ->assertDontSee('Receive inventory');
    }

    public function test_they_reach_their_pages_and_not_the_admin_ones(): void
    {
        foreach ([Timekeeping::class, InventoryItemResource::class, ShowLeaderboard::class] as $page) {
            $this->assertTrue($page::canAccess(), class_basename($page).' should open');
        }
        foreach ([PayoutResource::class, StreamerProfitShare::class, ManagerProfitShare::class, PalletResource::class, StockTransfer::class] as $page) {
            $this->assertFalse($page::canAccess(), class_basename($page).' should stay closed');
        }
        $this->assertFalse(InventoryItemResource::canCreate());

        $this->get(Timekeeping::getUrl())->assertOk();
        $this->get(PayoutResource::getUrl('index'))->assertForbidden();
    }

    public function test_a_list_saved_on_the_roles_screen_replaces_the_default(): void
    {
        NavVisibility::setVisibleForRole('streamer_assistant', [DashboardImproved::class, PalletResource::class]);
        NavVisibility::flushMemo();

        $this->assertFalse(NavVisibility::isHiddenForUser(PalletResource::class, $this->assistant));
        $this->assertTrue(NavVisibility::isHiddenForUser(InventoryItemResource::class, $this->assistant));
        // Their core workspace (shows, time, pay) stays on regardless.
        $this->assertFalse(NavVisibility::isHiddenForUser(Timekeeping::class, $this->assistant));
    }

    public function test_the_calendar_shows_every_show_without_sales(): void
    {
        $theirs = Show::create(['title' => 'Prizm Pack Wars', 'show_date' => now()->toDateString(), 'start_time' => '01:00:00', 'status' => 'reconciled', 'gross_revenue' => 4321, 'whatnot_net' => 3210]);
        $theirs->streamers()->attach($this->streamer->id);
        $other = Show::create(['title' => 'Another Host Show', 'show_date' => now()->toDateString(), 'status' => 'mapping']);
        $other->streamers()->attach(Streamer::create(['name' => 'Other', 'status' => 'active'])->id);

        $this->assertTrue(\App\Filament\Pages\Shows::canAccess());
        $page = Livewire::test(\App\Filament\Pages\Shows::class)->assertOk();
        // All shows, as on their All Shows list.
        $this->assertEqualsCanonicalizing(['Prizm Pack Wars', 'Another Host Show'], $page->instance()->shows->pluck('title')->all());
        $page->assertDontSee('4,321')->assertDontSee('Whatnot gross');
        $presented = $page->instance()->present($theirs->fresh(), now());
        $this->assertNull($presented['gross']);
        $this->assertNotNull($presented['viewUrl']);
    }
}
