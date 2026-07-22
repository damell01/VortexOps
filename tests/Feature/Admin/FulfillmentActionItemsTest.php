<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\ShowResource;
use App\Filament\Resources\ShowResource\Pages\ListShows;
use App\Filament\Resources\StreamerLogResource;
use App\Filament\Widgets\FulfillmentNeedsAttentionWidget;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\StreamerLogEntry;
use App\Models\User;
use App\Models\WhatnotShowOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * "Needs Fulfillment" / "Next Step" — the clear, role-scoped action buttons
 * for admins and the fulfillment team, so a show/log entry in review has an
 * obvious single click to the right place instead of requiring a click into
 * every row to discover what to do.
 */
class FulfillmentActionItemsTest extends TestCase
{
    use RefreshDatabase;

    private User $creator;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'streamer', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'fulfillment', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'fulfillment_admin', 'guard_name' => 'web']);
        $this->creator = User::factory()->create();
    }

    private function admin(): User
    {
        $u = User::factory()->create(['email' => 'admin@test.com']);
        $u->assignRole('admin');
        return $u;
    }

    private function fulfillmentAdmin(): User
    {
        $u = User::factory()->create(['email' => 'fulfillment-admin@test.com']);
        $u->assignRole('fulfillment_admin');
        return $u;
    }

    private function streamerUser(): User
    {
        $u = User::factory()->create(['email' => 'streamer@test.com']);
        $u->assignRole('streamer');
        return $u;
    }

    private function show(array $attrs = []): Show
    {
        return Show::create(array_merge([
            'title'      => 'Test Show',
            'show_date'  => now()->toDateString(),
            'status'     => 'pending_review',
            'created_by' => $this->creator->id,
        ], $attrs));
    }

    // ── StreamerLogResource access ──────────────────────────────────────────

    public function test_fulfillment_admin_can_now_access_streamer_log_resource(): void
    {
        $this->actingAs($this->fulfillmentAdmin());
        $this->assertTrue(StreamerLogResource::canAccess());
    }

    public function test_plain_fulfillment_still_cannot_access_streamer_log_resource(): void
    {
        $u = User::factory()->create(['email' => 'fulfillment@test.com']);
        $u->assignRole('fulfillment');
        $this->actingAs($u);

        $this->assertFalse(StreamerLogResource::canAccess());
    }

    public function test_streamer_can_still_access_streamer_log_resource(): void
    {
        $this->actingAs($this->streamerUser());
        $this->assertTrue(StreamerLogResource::canAccess());
    }

    // ── Fulfillment review action visibility ────────────────────────────────

    public function test_fulfillment_admin_sees_fulfillment_review_action_on_pwe_labels_entry(): void
    {
        $streamer = Streamer::create(['name' => 'PWE Bob', 'status' => 'active', 'payout_type' => 'pwe_labels']);
        $show     = $this->show(['status' => 'reconciled']);
        $entry    = StreamerLogEntry::create([
            'show_id' => $show->id, 'streamer_id' => $streamer->id, 'status' => 'admin_approved',
        ]);

        $this->assertTrue($entry->needsFulfillmentReview());

        $this->actingAs($this->fulfillmentAdmin());

        Livewire::test(\App\Filament\Resources\StreamerLogResource\Pages\ListStreamerLogEntries::class)
            ->assertTableActionVisible('fulfillment_review', $entry);
    }

    // ── Shows table "Next Step" action ──────────────────────────────────────

    public function test_next_step_action_visible_and_labeled_for_pending_review(): void
    {
        $show = $this->show(['status' => 'pending_review']);

        $this->actingAs($this->admin());

        Livewire::test(ListShows::class)
            ->set('activeTab', 'all')
            ->assertTableActionVisible('next_step', $show)
            ->assertTableActionHasLabel('next_step', 'Map Items', $show);
    }

    public function test_next_step_action_routes_pending_approval_to_deduction_requests(): void
    {
        $show = $this->show(['status' => 'pending_approval']);

        $this->actingAs($this->admin());

        Livewire::test(ListShows::class)
            ->set('activeTab', 'all')
            ->assertTableActionVisible('next_step', $show)
            ->assertTableActionHasLabel('next_step', 'Review Approval', $show);
    }

    public function test_next_step_action_hidden_for_draft_and_cancelled(): void
    {
        $draft = $this->show(['status' => 'draft']);
        $cancelled = $this->show(['status' => 'cancelled']);

        $this->actingAs($this->admin());

        Livewire::test(ListShows::class)
            ->set('activeTab', 'all')
            ->assertTableActionHidden('next_step', $draft)
            ->assertTableActionHidden('next_step', $cancelled);
    }

    public function test_next_step_action_hidden_for_non_admin(): void
    {
        $show = $this->show(['status' => 'pending_review']);
        $streamerModel = Streamer::create(['name' => 'S', 'status' => 'active']);
        $user = $this->streamerUser();
        $streamerModel->update(['user_id' => $user->id]);
        $show->streamers()->attach($streamerModel->id, ['is_primary' => true]);

        $this->actingAs($user);

        Livewire::test(ListShows::class)
            ->set('activeTab', 'all')
            ->assertTableActionHidden('next_step', $show);
    }

    // ── FulfillmentNeedsAttentionWidget ──────────────────────────────────────

    public function test_fulfillment_needs_attention_widget_visible_only_to_fulfillment_roles(): void
    {
        $this->actingAs($this->fulfillmentAdmin());
        $this->assertTrue(FulfillmentNeedsAttentionWidget::canView());

        $fulfillment = User::factory()->create(['email' => 'f2@test.com']);
        $fulfillment->assignRole('fulfillment');
        $this->actingAs($fulfillment);
        $this->assertTrue(FulfillmentNeedsAttentionWidget::canView());

        $this->actingAs($this->admin());
        $this->assertFalse(FulfillmentNeedsAttentionWidget::canView());

        $this->actingAs($this->streamerUser());
        $this->assertFalse(FulfillmentNeedsAttentionWidget::canView());
    }

    public function test_fulfillment_needs_attention_widget_counts_unshipped_orders(): void
    {
        $show = $this->show(['status' => 'reconciled']);
        WhatnotShowOrder::create([
            'show_id' => $show->id, 'buyer_username' => 'buyer1', 'item_name' => 'Card',
            'quantity' => 1, 'total_price' => 10, 'status' => 'completed',
            'show_date' => $show->show_date, 'shipping_status' => null,
        ]);
        WhatnotShowOrder::create([
            'show_id' => $show->id, 'buyer_username' => 'buyer2', 'item_name' => 'Card 2',
            'quantity' => 1, 'total_price' => 10, 'status' => 'completed',
            'show_date' => $show->show_date, 'shipping_status' => 'delivered',
        ]);

        $this->actingAs($this->fulfillmentAdmin());

        Livewire::test(FulfillmentNeedsAttentionWidget::class)
            ->assertSee('1')
            ->assertSee('orders still need a label, pack, or ship step');
    }
}
