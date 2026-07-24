<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\DeductionRequestResource;
use App\Filament\Resources\InventoryItemResource;
use App\Filament\Resources\PalletResource;
use App\Filament\Resources\PayoutResource;
use App\Filament\Resources\RoleResource;
use App\Filament\Resources\ShowResource;
use App\Filament\Resources\StreamerResource;
use App\Filament\Resources\UserResource;
use App\Filament\Resources\VendorResource;
use App\Filament\Resources\WhatnotChannelResource;
use App\Models\DeductionRequest;
use App\Models\InventoryCase;
use App\Models\InventoryItem;
use App\Models\InventoryStock;
use App\Models\Pallet;
use App\Models\PalletLine;
use App\Models\Payout;
use App\Models\ShippingSurcharge;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\StreamerLoan;
use App\Models\StreamerLogEntry;
use App\Models\User;
use App\Models\Vendor;
use App\Models\WhatnotChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Row-level delete was added to several resource tables that already had
 * (or should have had) the underlying canDelete()/canDeleteAny() logic but
 * no UI to actually use it — most notably Users. These tests cover the
 * protections that logic enforces.
 */
class CrudDeleteCompletenessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.owner_email' => 'owner@test.com']);
        foreach (['admin', 'super_admin', 'streamer', 'fulfillment', 'fulfillment_admin'] as $r) {
            Role::firstOrCreate(['name' => $r, 'guard_name' => 'web']);
        }
    }

    private function owner(): User
    {
        return User::factory()->create(['email' => 'owner@test.com']);
    }

    private function admin(): User
    {
        $u = User::factory()->create(['email' => 'admin@test.com']);
        $u->assignRole('admin');
        return $u;
    }

    // ── UserResource ─────────────────────────────────────────────────────────

    public function test_admin_cannot_delete_their_own_account(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $this->assertFalse(UserResource::canDelete($admin));
    }

    public function test_admin_cannot_delete_the_owner_account(): void
    {
        $owner = $this->owner();
        $this->actingAs($this->admin());

        $this->assertFalse(UserResource::canDelete($owner));
    }

    public function test_admin_cannot_delete_another_privileged_user(): void
    {
        $otherAdmin = User::factory()->create(['email' => 'other-admin@test.com']);
        $otherAdmin->assignRole('admin');

        $this->actingAs($this->admin());

        $this->assertFalse(UserResource::canDelete($otherAdmin));
    }

    public function test_owner_can_delete_a_privileged_user(): void
    {
        $otherAdmin = User::factory()->create(['email' => 'other-admin@test.com']);
        $otherAdmin->assignRole('admin');

        $this->actingAs($this->owner());

        $this->assertTrue(UserResource::canDelete($otherAdmin));
    }

    public function test_admin_can_delete_a_plain_non_privileged_user(): void
    {
        $streamerUser = User::factory()->create();
        $streamerUser->assignRole('streamer');

        $this->actingAs($this->admin());

        $this->assertTrue(UserResource::canDelete($streamerUser));
    }

    // ── RoleResource ─────────────────────────────────────────────────────────

    public function test_core_roles_are_protected_from_deletion(): void
    {
        foreach (RoleResource::CORE_ROLES as $name) {
            $this->assertTrue(RoleResource::isCoreRole($name));
        }
        $this->assertFalse(RoleResource::isCoreRole('seasonal-helper'));
    }

    // ── DeductionRequestResource ────────────────────────────────────────────

    private function deductionRequest(string $status): DeductionRequest
    {
        $creator = User::factory()->create();
        $show = Show::create(['title' => 'S', 'show_date' => now()->toDateString(), 'status' => 'reconciled', 'created_by' => $creator->id]);
        $streamer = Streamer::create(['name' => 'S', 'status' => 'active']);

        return DeductionRequest::create([
            'show_id' => $show->id,
            'streamer_id' => $streamer->id,
            'status' => $status,
        ]);
    }

    public function test_pending_deduction_request_can_be_deleted(): void
    {
        $this->actingAs($this->admin());
        $request = $this->deductionRequest('pending');

        $this->assertTrue(DeductionRequestResource::canDelete($request));
    }

    public function test_rejected_deduction_request_can_be_deleted(): void
    {
        $this->actingAs($this->admin());
        $request = $this->deductionRequest('rejected');

        $this->assertTrue(DeductionRequestResource::canDelete($request));
    }

    public function test_approved_deduction_request_cannot_be_deleted(): void
    {
        $this->actingAs($this->admin());
        $request = $this->deductionRequest('approved');

        $this->assertFalse(DeductionRequestResource::canDelete($request));
    }

    public function test_processed_deduction_request_cannot_be_deleted(): void
    {
        $this->actingAs($this->admin());
        $request = $this->deductionRequest('processed');

        $this->assertFalse(DeductionRequestResource::canDelete($request));
    }

    // ── StreamerResource ─────────────────────────────────────────────────────

    private function showWithCreator(): Show
    {
        return Show::create([
            'title' => 'S', 'show_date' => now()->toDateString(),
            'status' => 'draft', 'created_by' => auth()->id(),
        ]);
    }

    public function test_non_admin_cannot_delete_a_streamer_even_with_no_history(): void
    {
        $this->actingAs(User::factory()->create());
        $streamer = Streamer::create(['name' => 'Fresh', 'status' => 'active']);

        $this->assertFalse(StreamerResource::canDelete($streamer));
    }

    public function test_streamer_with_no_history_can_be_deleted(): void
    {
        $this->actingAs($this->admin());
        $streamer = Streamer::create(['name' => 'Fresh', 'status' => 'active']);

        $this->assertTrue(StreamerResource::canDelete($streamer));
    }

    public function test_streamer_attached_to_a_show_cannot_be_deleted(): void
    {
        $this->actingAs($this->admin());
        $streamer = Streamer::create(['name' => 'Has Show', 'status' => 'active']);
        $this->showWithCreator()->streamers()->attach($streamer->id, ['is_primary' => true]);

        $this->assertFalse(StreamerResource::canDelete($streamer));
    }

    public function test_streamer_with_a_payout_cannot_be_deleted(): void
    {
        $this->actingAs($this->admin());
        $streamer = Streamer::create(['name' => 'Has Payout', 'status' => 'active']);
        Payout::create(['streamer_id' => $streamer->id, 'payout_type' => 'flat_rate']);

        $this->assertFalse(StreamerResource::canDelete($streamer));
    }

    public function test_streamer_with_a_loan_cannot_be_deleted(): void
    {
        $this->actingAs($this->admin());
        $streamer = Streamer::create(['name' => 'Has Loan', 'status' => 'active']);
        StreamerLoan::create([
            'streamer_id' => $streamer->id, 'label' => 'Advance',
            'original_amount' => 100, 'weekly_repayment' => 10, 'remaining_balance' => 100,
        ]);

        $this->assertFalse(StreamerResource::canDelete($streamer));
    }

    public function test_streamer_with_a_log_entry_cannot_be_deleted(): void
    {
        $this->actingAs($this->admin());
        $streamer = Streamer::create(['name' => 'Has Log', 'status' => 'active']);
        StreamerLogEntry::create(['show_id' => $this->showWithCreator()->id, 'streamer_id' => $streamer->id]);

        $this->assertFalse(StreamerResource::canDelete($streamer));
    }

    public function test_streamer_with_a_deduction_request_cannot_be_deleted(): void
    {
        $this->actingAs($this->admin());
        $streamer = Streamer::create(['name' => 'Has DR', 'status' => 'active']);
        DeductionRequest::create([
            'show_id' => $this->showWithCreator()->id, 'streamer_id' => $streamer->id, 'status' => 'pending',
        ]);

        $this->assertFalse(StreamerResource::canDelete($streamer));
    }

    public function test_streamer_with_a_shipping_surcharge_cannot_be_deleted(): void
    {
        $this->actingAs($this->admin());
        $streamer = Streamer::create(['name' => 'Has Surcharge', 'status' => 'active']);
        ShippingSurcharge::create([
            'show_id' => $this->showWithCreator()->id, 'streamer_id' => $streamer->id,
            'package_count' => 1, 'rate_per_package' => 4, 'total_amount' => 4,
        ]);

        $this->assertFalse(StreamerResource::canDelete($streamer));
    }

    // ── VendorResource ───────────────────────────────────────────────────────

    public function test_vendor_with_no_pallets_can_be_deleted(): void
    {
        $this->actingAs($this->admin());
        $vendor = Vendor::create(['name' => 'V', 'status' => 'active']);

        $this->assertTrue(VendorResource::canDelete($vendor));
    }

    public function test_vendor_with_a_pallet_cannot_be_deleted(): void
    {
        $admin  = $this->admin();
        $this->actingAs($admin);
        $vendor = Vendor::create(['name' => 'V', 'status' => 'active']);
        Pallet::create(['vendor_id' => $vendor->id, 'status' => 'pending', 'created_by' => $admin->id]);

        $this->assertFalse(VendorResource::canDelete($vendor));
    }

    // ── WhatnotChannelResource ───────────────────────────────────────────────

    public function test_channel_with_no_shows_or_streamers_can_be_deleted(): void
    {
        $this->actingAs($this->admin());
        $channel = WhatnotChannel::create(['name' => 'C', 'status' => 'active']);

        $this->assertTrue(WhatnotChannelResource::canDelete($channel));
    }

    public function test_channel_with_a_show_cannot_be_deleted(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $channel = WhatnotChannel::create(['name' => 'C', 'status' => 'active']);
        Show::create([
            'whatnot_channel_id' => $channel->id, 'title' => 'S', 'show_date' => now()->toDateString(),
            'status' => 'draft', 'created_by' => $admin->id,
        ]);

        $this->assertFalse(WhatnotChannelResource::canDelete($channel));
    }

    public function test_channel_with_a_streamer_cannot_be_deleted(): void
    {
        $this->actingAs($this->admin());
        $channel = WhatnotChannel::create(['name' => 'C', 'status' => 'active']);
        Streamer::create(['name' => 'S', 'status' => 'active', 'whatnot_channel_id' => $channel->id]);

        $this->assertFalse(WhatnotChannelResource::canDelete($channel));
    }

    // ── InventoryItemResource ────────────────────────────────────────────────

    public function test_item_with_no_stock_can_be_deleted(): void
    {
        $this->actingAs($this->admin());
        $item = InventoryItem::create(['sku' => 'SKU1', 'name' => 'Item', 'is_active' => true]);

        $this->assertTrue(InventoryItemResource::canDelete($item));
    }

    public function test_item_with_stock_cannot_be_deleted(): void
    {
        $this->actingAs($this->admin());
        $item     = InventoryItem::create(['sku' => 'SKU2', 'name' => 'Item', 'is_active' => true]);
        $location = \App\Models\InventoryLocation::create(['name' => 'L', 'type' => 'warehouse', 'status' => 'active']);
        InventoryStock::create(['inventory_item_id' => $item->id, 'inventory_location_id' => $location->id, 'quantity' => 5]);

        $this->assertFalse(InventoryItemResource::canDelete($item));
    }

    // ── PalletResource ───────────────────────────────────────────────────────

    public function test_pallet_with_no_received_cases_can_be_deleted(): void
    {
        $admin  = $this->admin();
        $this->actingAs($admin);
        $pallet = Pallet::create(['status' => 'pending', 'created_by' => $admin->id]);

        $this->assertTrue(PalletResource::canDelete($pallet));
    }

    public function test_pallet_with_received_cases_cannot_be_deleted(): void
    {
        $admin  = $this->admin();
        $this->actingAs($admin);
        $pallet = Pallet::create(['status' => 'receiving', 'created_by' => $admin->id]);
        $line   = PalletLine::create(['pallet_id' => $pallet->id, 'line_number' => 1, 'case_count' => 1, 'description' => 'Line']);
        InventoryCase::create(['pallet_line_id' => $line->id, 'status' => 'received']);

        $this->assertFalse(PalletResource::canDelete($pallet));
    }

    // ── PayoutResource ───────────────────────────────────────────────────────

    private function payout(string $status): Payout
    {
        $streamer = Streamer::create(['name' => 'S', 'status' => 'active']);

        return Payout::create(['streamer_id' => $streamer->id, 'payout_type' => 'flat_rate', 'status' => $status]);
    }

    public function test_draft_payout_can_be_deleted(): void
    {
        $this->actingAs($this->admin());

        $this->assertTrue(PayoutResource::canDelete($this->payout('draft')));
    }

    public function test_approved_payout_cannot_be_deleted(): void
    {
        $this->actingAs($this->admin());

        $this->assertFalse(PayoutResource::canDelete($this->payout('approved')));
    }

    public function test_paid_payout_cannot_be_deleted(): void
    {
        $this->actingAs($this->admin());

        $this->assertFalse(PayoutResource::canDelete($this->payout('paid')));
    }

    // ── ShowResource ─────────────────────────────────────────────────────────

    public function test_empty_draft_show_can_be_deleted(): void
    {
        $this->actingAs($this->admin());
        $show = $this->showWithCreator();

        $this->assertTrue(ShowResource::canDelete($show));
    }

    public function test_show_with_orders_cannot_be_deleted(): void
    {
        $this->actingAs($this->admin());
        $show = $this->showWithCreator();
        \App\Models\WhatnotShowOrder::create(['show_id' => $show->id, 'quantity' => 1, 'total_price' => 10, 'total_cost' => 0, 'show_date' => $show->show_date]);

        $this->assertFalse(ShowResource::canDelete($show));
    }

    public function test_show_with_a_streamer_attached_cannot_be_deleted(): void
    {
        $this->actingAs($this->admin());
        $show     = $this->showWithCreator();
        $streamer = Streamer::create(['name' => 'S', 'status' => 'active']);
        $show->streamers()->attach($streamer->id, ['is_primary' => true]);

        $this->assertFalse(ShowResource::canDelete($show));
    }

    public function test_show_with_a_deduction_request_cannot_be_deleted(): void
    {
        $this->actingAs($this->admin());
        $show     = $this->showWithCreator();
        $streamer = Streamer::create(['name' => 'S', 'status' => 'active']);
        DeductionRequest::create(['show_id' => $show->id, 'streamer_id' => $streamer->id, 'status' => 'pending']);

        $this->assertFalse(ShowResource::canDelete($show));
    }

    public function test_non_admin_cannot_delete_an_empty_show_either(): void
    {
        $this->actingAs(User::factory()->create());
        $show = Show::create(['title' => 'S', 'show_date' => now()->toDateString(), 'status' => 'draft', 'created_by' => $this->admin()->id]);

        $this->assertFalse(ShowResource::canDelete($show));
    }
}
