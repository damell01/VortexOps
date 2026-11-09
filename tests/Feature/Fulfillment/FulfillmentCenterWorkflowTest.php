<?php

namespace Tests\Feature\Fulfillment;

use App\Filament\Pages\FulfillmentActivity;
use App\Filament\Pages\FulfillmentHome;
use App\Filament\Pages\FulfillmentStationBoard;
use App\Models\FulfillmentEvent;
use App\Models\FulfillmentRun;
use App\Models\FulfillmentStation;
use App\Models\Setting;
use App\Models\Shipment;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\StreamerLogEntry;
use App\Models\StreamerLogItem;
use App\Models\User;
use App\Models\WhatnotShowOrder;
use App\Services\FulfillmentFlagService;
use App\Services\FulfillmentQueue;
use App\Services\FulfillmentRunService;
use App\Services\FulfillmentScheduleService;
use App\Support\AdminModules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * The Fulfillment Center end to end: open a show, start packing, letter the
 * buyers, pack, flag, finish, approve, and everything recorded on the way.
 */
class FulfillmentCenterWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;
    private User $packer;
    private User $helper;
    private FulfillmentStation $station;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['admin', 'fulfillment', 'fulfillment_admin', 'streamer'] as $role) Role::findOrCreate($role, 'web');
        Setting::set('enabled_admin_modules', json_encode(['streams', 'fulfillment', 'inventory']));
        AdminModules::flushMemo();
        $this->manager = User::factory()->create(['name' => 'Crystal Rice']);
        $this->manager->assignRole('fulfillment_admin');
        $this->packer = User::factory()->create(['name' => 'Dennis Packer']);
        $this->packer->assignRole('fulfillment');
        $this->helper = User::factory()->create(['name' => 'Monica Helper']);
        $this->helper->assignRole('fulfillment');
        $this->station = FulfillmentStation::query()->orderBy('sort')->firstOrFail();
    }

    protected function tearDown(): void
    {
        AdminModules::flushMemo();
        parent::tearDown();
    }

    /** An ended show with an approved report, orders for the given buyers, and optional shipments. */
    private function show(array $buyers = ['zoe_cards' => 1, 'Alpha_Rips' => 2, 'bigpullz' => 1], bool $shipments = true, string $date = null): Show
    {
        $show = Show::create(['title' => 'Big Giveys Top Hit Tuesday', 'show_date' => $date ?? today()->subDay()->toDateString(), 'status' => 'reconciled', 'is_operational' => true]);
        $streamer = Streamer::create(['name' => 'Jason', 'status' => 'active']);
        $show->streamers()->attach($streamer->id, ['is_primary' => true]);
        $log = StreamerLogEntry::create(['show_id' => $show->id, 'streamer_id' => $streamer->id, 'status' => 'admin_approved', 'approval_status' => 'approved',
            'submitted_at' => now()->subHour(), 'streamer_reviewed_at' => now()->subHour()]);
        StreamerLogItem::create(['streamer_log_entry_id' => $log->id, 'item_name' => 'Pack', 'quantity' => 1, 'packed_quantity' => 0, 'unit_cost' => 1,
            'disposition' => 'sold', 'fulfillment_status' => StreamerLogItem::FULFILLMENT_PENDING]);
        $n = 0;
        foreach ($buyers as $username => $orders) {
            for ($i = 0; $i < $orders; $i++) {
                $n++;
                $order = WhatnotShowOrder::create(['show_id' => $show->id, 'buyer_username' => $username, 'whatnot_order_id' => 'ORD-' . $show->id . '-' . $n,
                    'item_name' => 'Pack', 'quantity' => 1, 'total_price' => 5, 'status' => 'completed', 'show_date' => $show->show_date]);
                if ($shipments) {
                    Shipment::create(['show_id' => $show->id, 'whatnot_order_id' => $order->id, 'buyer_username' => $username, 'item_count' => 1, 'status' => 'label_created']);
                }
            }
        }
        $show->fulfillmentUsers()->sync([$this->packer->id]);

        return $show->fresh();
    }

    private function started(?Show $show = null, ?User $by = null): FulfillmentRun
    {
        $show ??= $this->show();
        $id = app(FulfillmentRunService::class)->start($show, $this->station->id, $by ?? $this->packer);

        return FulfillmentRun::query()->findOrFail($id);
    }

    public function test_buyers_are_lettered_alphabetically_once_per_username_with_orders_grouped(): void
    {
        $run = $this->started($this->show(['zoe_cards' => 1, 'Alpha_Rips' => 2, 'bigpullz' => 1, 'alpha_rips' => 1]));
        $buyers = $run->buyers()->get();

        // Case-insensitive: Alpha_Rips and alpha_rips are one buyer with three orders.
        $this->assertSame(['A', 'B', 'C'], $buyers->pluck('letter')->all());
        $this->assertSame(['Alpha_Rips', 'bigpullz', 'zoe_cards'], $buyers->pluck('buyer_username')->all());
        $this->assertSame(3, $buyers->first()->order_count);
        $this->assertSame(3, $buyers->first()->package_count);
        $this->assertSame(5, $run->fresh()->expected_packages);
        $this->assertSame(3, $run->fresh()->buyer_count);
    }

    public function test_letters_continue_past_z_and_stay_put_after_packing_starts(): void
    {
        $names = [];
        foreach (range(1, 28) as $i) $names[sprintf('buyer%02d', $i)] = 1;
        $show = $this->show($names, false);
        $run = $this->started($show);
        $this->assertSame('AB', $run->buyers()->get()->last()->letter);

        // A later order sync must not relabel a started run.
        WhatnotShowOrder::create(['show_id' => $show->id, 'buyer_username' => 'aaa_first', 'whatnot_order_id' => 'LATE-1', 'item_name' => 'Pack', 'quantity' => 1, 'total_price' => 5, 'status' => 'completed', 'show_date' => $show->show_date]);
        $this->assertSame('A', $run->buyers()->where('buyer_username', 'buyer01')->value('letter'));
        $this->assertFalse($run->buyers()->where('buyer_username', 'aaa_first')->exists());
    }

    public function test_start_records_station_worker_time_and_an_activity_trail(): void
    {
        $this->travelTo(now()->setTime(14, 5));
        $run = $this->started();

        $this->assertSame(FulfillmentRun::PACKING, $run->status);
        $this->assertSame($this->station->id, $run->station_occupied_id);
        $this->assertEquals(now()->toDateTimeString(), $run->started_at->toDateTimeString());
        $this->assertTrue($run->packers->contains($this->packer));
        $this->assertSame(['ready', 'started', 'buyers_loaded'], FulfillmentEvent::query()->where('run_id', $run->id)->orderBy('id')->pluck('type')->all());
    }

    public function test_a_package_marked_packed_twice_counts_once(): void
    {
        $run = $this->started();
        $package = $run->packages()->firstOrFail();
        $service = app(FulfillmentRunService::class);
        $service->packPackage($run->id, $package->id, $this->packer);
        $service->packPackage($run->id, $package->id, $this->packer);

        $this->assertSame(1, $run->fresh()->completed_packages);
        $this->assertSame(1, FulfillmentEvent::query()->where('run_id', $run->id)->where('type', 'package_packed')->count());
    }

    public function test_shared_show_credits_each_package_to_whoever_packed_it(): void
    {
        $show = $this->show();
        $show->fulfillmentUsers()->sync([$this->packer->id, $this->helper->id]);
        $run = $this->started($show);
        $service = app(FulfillmentRunService::class);
        $buyers = $run->buyers()->get();
        $service->markPacked($run->id, $buyers[0]->id, $this->packer);   // Alpha: 2 packages
        $service->markPacked($run->id, $buyers[1]->id, $this->helper);   // bigpullz: 1
        $service->markPacked($run->id, $buyers[1]->id, $this->packer);   // already packed: no-op

        $credit = DB::table('fulfillment_run_packages')->where('run_id', $run->id)->where('status', 'packed')
            ->select('packed_by', DB::raw('COUNT(*) as n'))->groupBy('packed_by')->pluck('n', 'packed_by');
        $this->assertSame(2, (int) $credit[$this->packer->id]);
        $this->assertSame(1, (int) $credit[$this->helper->id]);
        $this->assertSame(3, $run->fresh()->completed_packages);
        $this->assertSame($this->helper->id, $buyers[1]->fresh()->packed_by);
    }

    public function test_a_blocking_issue_holds_the_buyer_and_the_show_until_resolved(): void
    {
        $run = $this->started();
        $buyer = $run->buyers()->firstOrFail();
        $flags = app(FulfillmentFlagService::class);
        $flagId = $flags->report($this->packer, $run->show_id, 'missing_item', 'Not on the shelf', $run->id, $buyer->id, 'ORD-1');
        $this->assertSame('held', $buyer->fresh()->status);

        try {
            app(FulfillmentRunService::class)->markPacked($run->id, $buyer->id, $this->packer);
            $this->fail('A held buyer was packed.');
        } catch (ValidationException) {
        }
        foreach ($run->buyers()->where('id', '!=', $buyer->id)->get() as $other) {
            app(FulfillmentRunService::class)->markPacked($run->id, $other->id, $this->packer);
        }
        $this->expectFinishBlocked($run);

        // Only a manager resolves it.
        try {
            $flags->resolve($this->packer, $flagId, 'found it');
            $this->fail('A packer resolved an issue.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
        $flags->resolve($this->manager, $flagId, 'Found in the back room');
        $this->assertSame('waiting', $buyer->fresh()->status);
        app(FulfillmentRunService::class)->markPacked($run->id, $buyer->id, $this->packer);
        app(FulfillmentRunService::class)->finish($run->id, $this->packer);
        $this->assertSame(FulfillmentRun::COMPLETED, $run->fresh()->status);
        $this->assertContains('issue_resolved', FulfillmentEvent::query()->where('run_id', $run->id)->pluck('type')->all());
    }

    private function expectFinishBlocked(FulfillmentRun $run): void
    {
        try {
            app(FulfillmentRunService::class)->finish($run->id, $this->packer);
            $this->fail('Finished with an open blocking issue.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Resolve open issues', collect($e->errors())->flatten()->first());
        }
    }

    public function test_finish_records_completion_frees_the_station_and_only_managers_approve(): void
    {
        $this->travelTo(now()->setTime(14, 5));
        $run = $this->started();
        foreach ($run->buyers()->get() as $buyer) app(FulfillmentRunService::class)->markPacked($run->id, $buyer->id, $this->packer);
        $this->travelTo(now()->setTime(16, 12));
        app(FulfillmentRunService::class)->finish($run->id, $this->packer);
        $run->refresh();

        $this->assertSame(FulfillmentRun::COMPLETED, $run->status);
        $this->assertNull($run->station_occupied_id);
        $this->assertSame('16:12', $run->finished_at->format('H:i'));
        $this->assertSame(4, $run->completed_packages);
        $this->assertNull($run->discrepancies);

        app(FulfillmentRunService::class)->acknowledge($run->id, $this->packer);
        try {
            app(FulfillmentRunService::class)->approve($run->id, $this->packer);
            $this->fail('A packer approved their own record.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
        app(FulfillmentRunService::class)->approve($run->id, $this->manager, 'Counts match the labels');
        $run->refresh();
        $this->assertSame(FulfillmentRun::APPROVED, $run->status);
        $this->assertNotNull($run->acknowledged_at);
        $this->assertSame($this->manager->id, $run->approved_by);
    }

    public function test_pause_time_is_left_out_of_packing_time(): void
    {
        $this->travelTo(now()->setTime(10, 0));
        $run = $this->started();
        $this->travelTo(now()->setTime(10, 30));
        app(FulfillmentRunService::class)->pause($run->id, $this->packer, 'Lunch');
        $this->travelTo(now()->setTime(11, 0));
        app(FulfillmentRunService::class)->resume($run->id, $this->packer);
        $this->travelTo(now()->setTime(11, 15));

        $this->assertSame(1800, $run->fresh()->paused_seconds);
        $this->assertSame(45 * 60, $run->fresh()->activeSeconds());
    }

    public function test_a_station_runs_one_show_at_a_time_and_a_member_cannot_start_unassigned_work(): void
    {
        $this->started();
        try {
            $this->started($this->show(['other' => 1]));
            $this->fail('Two shows on one station.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('already packing', collect($e->errors())->flatten()->first());
        }

        $unassigned = $this->show(['someone' => 1]);
        $unassigned->fulfillmentUsers()->sync([]);
        $this->expectException(HttpException::class);
        app(FulfillmentRunService::class)->start($unassigned, FulfillmentStation::query()->skip(1)->first()->id, $this->helper);
    }

    public function test_without_shipment_records_packing_is_tracked_per_buyer_and_totals_stay_pending(): void
    {
        $run = $this->started($this->show(['a' => 1, 'b' => 1], false));
        $this->assertNull($run->expected_packages);
        $this->assertSame(0, $run->packages()->count());
        foreach ($run->buyers()->get() as $buyer) app(FulfillmentRunService::class)->markPacked($run->id, $buyer->id, $this->packer);
        app(FulfillmentRunService::class)->finish($run->id, $this->packer);
        $this->assertSame(2, $run->buyers()->where('status', 'packed')->count());
    }

    public function test_queue_sync_opens_one_record_per_ready_show_and_announces_it(): void
    {
        $show = $this->show();
        $queue = app(FulfillmentQueue::class);
        $this->assertSame(1, $queue->sync());
        $this->assertSame(0, $queue->sync());
        $run = FulfillmentRun::query()->where('show_id', $show->id)->sole();
        $this->assertSame(FulfillmentRun::READY, $run->status);
        $this->assertNotNull($run->ready_notified_at);
        $this->assertTrue($this->packer->notifications()->exists());
    }

    public function test_overnight_shifts_are_one_shift_into_the_next_day(): void
    {
        $id = app(FulfillmentScheduleService::class)->create($this->manager, $this->packer->id, '2026-11-10 01:00:00', '2026-11-10 05:00:00', $this->station->id, null, [], 'night');
        $shift = \App\Models\FulfillmentShift::query()->findOrFail($id);
        $this->assertSame('night', $shift->kind);

        // 7 PM – 3 AM Central entered on the page.
        Livewire::actingAs($this->manager)->test(FulfillmentActivity::class)
            ->call('newShift', '2026-11-12', $this->helper->id)
            ->set('shift.start', '19:00')->set('shift.end', '03:00')->set('shift.station_id', (string) $this->station->id)
            ->call('saveShift')->assertHasNoErrors();
        $night = \App\Models\FulfillmentShift::query()->where('user_id', $this->helper->id)->sole();
        $this->assertTrue($night->crossesMidnight() || $night->ends_at->greaterThan($night->starts_at));
        $this->assertSame(8.0, (float) $night->starts_at->diffInHours($night->ends_at));
        $this->assertSame('night', $night->kind);
        $this->assertTrue($this->helper->notifications()->exists());
    }

    public function test_tv_feed_is_read_only_and_shows_only_whiteboard_details(): void
    {
        $run = $this->started();
        app(FulfillmentRunService::class)->selectBuyer($run->id, $run->buyers()->firstOrFail()->id, $this->packer);

        $this->actingAs($this->packer)->get(route('fulfillment.whiteboard'))->assertOk()->assertSee('Fulfillment whiteboard');
        $json = $this->actingAs($this->packer)->getJson(route('fulfillment.whiteboard.feed'))->assertOk()->json();
        $card = collect($json['stations'])->firstWhere('id', $this->station->id);
        $this->assertSame('A', $card['letter']);
        $this->assertSame('Alpha_Rips', $card['buyer']);
        $this->assertSame([
            'id', 'name', 'status', 'issue', 'show', 'stream', 'started', 'packer', 'letter', 'buyer', 'done', 'total', 'unit', 'pct',
        ], array_keys($card));
        $this->actingAs($this->packer)->post(route('fulfillment.whiteboard'))->assertStatus(405);

        $outsider = User::factory()->create();
        $outsider->assignRole('streamer');
        $this->actingAs($outsider)->get(route('fulfillment.whiteboard'))->assertForbidden();
    }

    public function test_pages_render_for_a_manager_and_a_member(): void
    {
        $run = $this->started();
        foreach ([$this->manager, $this->packer] as $user) {
            Livewire::actingAs($user)->test(FulfillmentHome::class)->assertOk()->assertSee('Big Giveys Top Hit Tuesday');
            Livewire::actingAs($user)->test(FulfillmentStationBoard::class, ['runId' => $run->id])->assertOk()->assertSee('Alpha_Rips');
            foreach (array_keys(FulfillmentActivity::TABS) as $tab) {
                Livewire::actingAs($user)->test(FulfillmentActivity::class)->set('tab', $tab)->assertOk();
            }
        }
        Livewire::actingAs($this->manager)->test(FulfillmentActivity::class, ['runId' => $run->id])->assertOk()->assertSee('Show log');
    }

    public function test_station_board_packs_buyer_by_buyer_and_moves_to_the_next_letter(): void
    {
        $run = $this->started();
        $page = Livewire::actingAs($this->packer)->test(FulfillmentStationBoard::class, ['runId' => $run->id]);
        $page->call('selectBuyer', $run->buyers()->where('letter', 'B')->value('id'))->call('packNext');
        $this->assertSame('packed', $run->buyers()->where('letter', 'B')->value('status'));
        $this->assertNotNull($run->fresh()->current_buyer_id);
        $this->assertNotSame($run->buyers()->where('letter', 'B')->value('id'), $run->fresh()->current_buyer_id);
    }

    public function test_footage_lookup_finds_where_and_when_an_order_was_packed(): void
    {
        $run = $this->started();
        $buyer = $run->buyers()->firstOrFail();
        app(FulfillmentRunService::class)->markPacked($run->id, $buyer->id, $this->packer);
        $orderId = $buyer->order_ids[0];

        $results = Livewire::actingAs($this->manager)->test(FulfillmentActivity::class)->set('tab', 'lookup')->set('lookup', $orderId)->instance()->lookupResults;
        $this->assertSame([$buyer->id], $results->pluck('id')->all());
        $this->assertNotNull($results->first()->packed_at);
    }

    public function test_events_are_append_only(): void
    {
        $run = $this->started();
        $event = FulfillmentEvent::query()->where('run_id', $run->id)->firstOrFail();
        $this->expectException(\LogicException::class);
        $event->update(['type' => 'tampered']);
    }

    public function test_assigning_notifies_newly_assigned_people_and_managers_only_assign(): void
    {
        $show = $this->show();
        app(FulfillmentRunService::class)->assign($show, [$this->packer->id, $this->helper->id], $this->manager, $this->station->id);
        $this->assertTrue($this->helper->notifications()->exists());
        $this->assertSame($this->station->id, FulfillmentRun::query()->where('show_id', $show->id)->value('station_id'));

        $this->expectException(HttpException::class);
        app(FulfillmentRunService::class)->assign($show, [$this->helper->id], $this->packer);
    }
}
