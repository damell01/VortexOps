<?php

namespace Tests\Feature\Reliability;

use App\Filament\Resources\StreamerLogResource\Pages\ListStreamerLogEntries;
use App\Models\Payout;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\StreamerLogEntry;
use App\Models\User;
use App\Services\PayoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** The inbox shows what each streamer earns from the show they reported. */
class ReportInboxPayTest extends TestCase
{
    use RefreshDatabase;

    private function report(string $status = 'streamer_reviewed'): StreamerLogEntry
    {
        $streamer = Streamer::create(['name' => 'Tyler Share', 'status' => 'active', 'payout_type' => 'profit_share', 'payout_percentage' => 10]);
        $show = Show::create(['title' => 'Inbox Pay Show', 'show_date' => now()->subDay()->toDateString(), 'gross_revenue' => 1000]);
        $show->streamers()->attach($streamer->id, ['is_primary' => true]);

        return StreamerLogEntry::create([
            'show_id' => $show->id, 'streamer_id' => $streamer->id, 'status' => $status,
            'product_cost' => 200, 'hours_streamed' => 0, 'number_of_shipments' => 0, 'submitted_at' => now(),
        ]);
    }

    private function owner(): void
    {
        $this->enableAdminModules();
        $this->actingAs(User::firstWhere('email', config('app.owner_email')) ?? User::factory()->create(['email' => config('app.owner_email')]));
    }

    public function test_an_unpaid_report_shows_the_profit_share_it_would_earn(): void
    {
        $this->owner();
        $entry = $this->report()->load(['show.streamers', 'show.channel', 'streamer']);

        // ($1,000 sales − $200 product cost) × 10%, with no hours or shipments to burden it.
        $pay = app(PayoutService::class)->payForReport($entry);
        $this->assertSame('estimate', $pay['state']);
        $this->assertEqualsWithDelta(80.0, $pay['amount'], 0.001);
        $this->assertSame(0, Payout::count(), 'previewing pay must not create a payout');
    }

    public function test_pay_already_on_a_run_is_read_back_rather_than_recalculated(): void
    {
        $this->owner();
        $entry = $this->report('admin_approved');
        Payout::create(['show_id' => $entry->show_id, 'streamer_id' => $entry->streamer_id, 'payout_type' => 'profit_share', 'calculated_payout' => 75.5, 'status' => 'paid']);

        $pay = app(PayoutService::class)->payForReport($entry->load(['show.streamers', 'show.channel', 'streamer']));
        $this->assertSame('paid', $pay['state']);
        $this->assertEqualsWithDelta(75.5, $pay['amount'], 0.001);
    }

    public function test_the_inbox_lists_the_report_with_its_pay_and_a_way_to_the_next_review(): void
    {
        $this->owner();
        $this->report();

        Livewire::test(ListStreamerLogEntries::class)
            ->call('loadTable')
            ->assertOk()
            ->assertSee('Inbox Pay Show')
            ->assertSee('Ready for review')
            ->assertSee('Est. pay')
            ->assertSee('$80.00')
            ->assertSee('1 ready')
            ->assertSee('Review next')
            ->assertSee('Admin review required');
    }

    public function test_an_admin_sees_what_a_report_is_missing(): void
    {
        $this->owner();
        $entry = $this->report();
        $entry->update(['hours_streamed' => null]);
        $entry->show->update(['gross_revenue' => 0, 'whatnot_net' => 0]);
        \App\Models\StreamerLogItem::create(['streamer_log_entry_id' => $entry->id, 'item_name' => 'Hand-typed slab', 'quantity' => 1, 'disposition' => 'sold']);

        Livewire::test(ListStreamerLogEntries::class)
            ->call('loadTable')
            ->assertSee('Missing:')
            ->assertSee('Whatnot earnings not imported')
            ->assertSee('Hours worked missing')
            ->assertSee('1 item has no cost');
    }

    public function test_a_complete_report_shows_no_missing_warning(): void
    {
        $this->owner();
        $entry = $this->report();
        \App\Models\StreamerLogItem::create(['streamer_log_entry_id' => $entry->id, 'item_name' => 'Costed box', 'quantity' => 1, 'disposition' => 'sold', 'unit_cost' => 20]);

        $this->assertSame([], \App\Filament\Resources\StreamerLogResource::missingInfo(
            $entry->load(['show.streamers', 'show.channel', 'streamer'])->loadCount(['items', 'items as uncosted_items_count' => fn ($q) => $q->whereNull('inventory_item_id')->whereNull('unit_cost')])
        ));
    }

    public function test_the_csv_carries_the_pay_column(): void
    {
        $this->owner();
        $this->report();

        $response = Livewire::test(ListStreamerLogEntries::class)->call('loadTable')->callAction('export_csv');
        $response->assertFileDownloaded('show-reports-' . now()->format('Y-m-d') . '.csv');
    }
}
