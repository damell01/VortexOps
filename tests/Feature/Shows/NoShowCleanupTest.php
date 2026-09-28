<?php

namespace Tests\Feature\Shows;

use App\Models\DeductionRequest;
use App\Models\FulfillmentPackage;
use App\Models\Payout;
use App\Models\Shipment;
use App\Models\ShippingSurcharge;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\StreamerLogEntry;
use App\Models\User;
use App\Models\WhatnotShowOrder;
use App\Filament\Pages\NoShowCleanup;
use App\Services\NoShowCleanupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A show only reaches status=cancelled + analytics_sync_status=unavailable
 * through WhatnotReportingReconciler confirming an explicit 0-minute
 * duration two-plus days after the scheduled date — the one signal that
 * means a show genuinely never aired. This is the only thing eligible for
 * permanent deletion here, and only when nothing real (an order, a payout,
 * a shipment, a deduction, a surcharge, a streamer report, a fulfillment
 * package) is attached to it — several of those cascade-delete at the
 * database level, and a no-show flag must never be what silently erases
 * real financial or fulfillment history.
 */
class NoShowCleanupTest extends TestCase
{
    use RefreshDatabase;

    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userId = User::factory()->create()->id;
    }

    private function service(): NoShowCleanupService
    {
        return app(NoShowCleanupService::class);
    }

    private function confirmedNoShow(array $overrides = []): Show
    {
        return Show::create(array_merge([
            'title' => 'Dead show',
            'show_date' => today()->subDays(5)->toDateString(),
            'created_by' => $this->userId,
            'status' => 'cancelled',
            'analytics_sync_status' => 'unavailable',
            'analytics_sync_note' => 'Automatically excluded: Whatnot reported a 0-minute duration at least 2 days after the scheduled show.',
        ], $overrides));
    }

    public function test_a_confirmed_no_show_is_listed_as_a_candidate(): void
    {
        $show = $this->confirmedNoShow();

        $this->assertTrue($this->service()->candidates()->pluck('id')->contains($show->id));
    }

    public function test_a_show_cancelled_for_a_different_reason_is_not_a_candidate(): void
    {
        Show::create([
            'title' => 'Cancelled for other reasons',
            'show_date' => today()->subDays(5)->toDateString(),
            'created_by' => $this->userId,
            'status' => 'cancelled',
            'analytics_sync_status' => 'unavailable',
            'analytics_sync_note' => 'Manually marked unavailable by an admin.',
        ]);

        $this->assertSame(0, $this->service()->candidates()->count());
    }

    public function test_a_show_with_nothing_attached_can_be_deleted(): void
    {
        $show = $this->confirmedNoShow();

        $this->assertTrue($this->service()->canDelete($show));

        $this->service()->delete($show);

        $this->assertDatabaseMissing('shows', ['id' => $show->id]);
    }

    public function test_an_order_blocks_deletion(): void
    {
        $show = $this->confirmedNoShow();
        WhatnotShowOrder::create(['show_id' => $show->id]);

        $this->assertFalse($this->service()->canDelete($show));
        $this->expectException(\RuntimeException::class);
        $this->service()->delete($show);
    }

    public function test_a_payout_blocks_deletion(): void
    {
        $show = $this->confirmedNoShow();
        $streamer = Streamer::create(['name' => 'Test Streamer', 'status' => 'active']);
        Payout::create(['show_id' => $show->id, 'streamer_id' => $streamer->id, 'payout_type' => 'flat_rate']);

        $this->assertFalse($this->service()->canDelete($show));
        $this->assertDatabaseHas('shows', ['id' => $show->id]);
    }

    public function test_a_shipment_blocks_deletion(): void
    {
        $show = $this->confirmedNoShow();
        Shipment::create(['show_id' => $show->id]);

        $this->assertFalse($this->service()->canDelete($show));
    }

    public function test_a_streamer_report_blocks_deletion(): void
    {
        $show = $this->confirmedNoShow();
        $streamer = Streamer::create(['name' => 'Test Streamer', 'status' => 'active']);
        StreamerLogEntry::create(['show_id' => $show->id, 'streamer_id' => $streamer->id]);

        $this->assertFalse($this->service()->canDelete($show));
    }

    public function test_a_fulfillment_package_blocks_deletion(): void
    {
        $show = $this->confirmedNoShow();
        FulfillmentPackage::create([
            'show_id' => $show->id, 'package_code' => 'PKG-TEST-1',
            'tracking_number' => 'TRACK-TEST-1', 'created_by' => $this->userId,
        ]);

        $this->assertFalse($this->service()->canDelete($show));
    }

    public function test_a_deduction_request_blocks_deletion(): void
    {
        $show = $this->confirmedNoShow();
        $streamer = Streamer::create(['name' => 'Test Streamer', 'status' => 'active']);
        DeductionRequest::create(['show_id' => $show->id, 'streamer_id' => $streamer->id]);

        $this->assertFalse($this->service()->canDelete($show));
    }

    public function test_a_shipping_surcharge_blocks_deletion(): void
    {
        $show = $this->confirmedNoShow();
        $streamer = Streamer::create(['name' => 'Test Streamer', 'status' => 'active']);
        ShippingSurcharge::create([
            'show_id' => $show->id, 'streamer_id' => $streamer->id,
            'package_count' => 1, 'rate_per_package' => 5, 'total_amount' => 5,
        ]);

        $this->assertFalse($this->service()->canDelete($show));
    }

    public function test_a_show_that_was_never_confirmed_as_a_no_show_cannot_be_deleted(): void
    {
        $show = Show::create([
            'title' => 'Still active',
            'show_date' => today()->subDays(5)->toDateString(),
            'created_by' => $this->userId,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->service()->delete($show);
    }

    public function test_delete_all_clear_removes_the_unblocked_ones_and_reports_the_rest(): void
    {
        $clear = $this->confirmedNoShow(['title' => 'Clear one']);
        $blocked = $this->confirmedNoShow(['title' => 'Blocked one']);
        WhatnotShowOrder::create(['show_id' => $blocked->id]);

        $result = $this->service()->deleteAllClear();

        $this->assertSame(1, $result['deleted']);
        $this->assertCount(1, $result['skipped']);
        $this->assertSame($blocked->id, $result['skipped'][0]->id);
        $this->assertDatabaseMissing('shows', ['id' => $clear->id]);
        $this->assertDatabaseHas('shows', ['id' => $blocked->id]);
    }

    public function test_the_page_renders_and_lets_an_admin_delete_a_clean_no_show(): void
    {
        $this->actingAs(User::factory()->create(['email' => 'dbellcreations@gmail.com']));
        $show = $this->confirmedNoShow();

        Livewire::test(NoShowCleanup::class)
            ->assertOk()
            ->assertSee($show->title)
            ->call('deleteShow', $show->id)
            ->assertOk();

        $this->assertDatabaseMissing('shows', ['id' => $show->id]);
    }

    public function test_a_non_admin_is_refused_access_to_the_page(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(NoShowCleanup::class)->assertStatus(403);
    }
}
