<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\FulfillmentResource;
use App\Filament\Resources\FulfillmentResource\Pages\ListFulfillmentShows;
use App\Filament\Resources\FulfillmentResource\Pages\ViewFulfillmentShow;
use App\Filament\Resources\FulfillmentResource\RelationManagers\FulfillmentOrdersRelationManager;
use App\Models\Setting;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\StreamerLogEntry;
use App\Models\User;
use App\Models\WhatnotChannel;
use App\Models\WhatnotShowOrder;
use App\Support\AdminModules;
use App\Support\ChannelContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FulfillmentCenterTest extends TestCase
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
        Setting::set('enabled_admin_modules', json_encode(['streams', 'fulfillment']));
        AdminModules::flushMemo();
        $this->creator = User::factory()->create();
    }

    private function admin(): User
    {
        $u = User::factory()->create(['email' => 'admin@test.com']);
        $u->assignRole('admin');
        return $u;
    }

    private function fulfillmentUser(string $email = 'fulfillment@test.com'): User
    {
        $u = User::factory()->create(['email' => $email]);
        $u->assignRole('fulfillment');
        return $u;
    }

    private function fulfillmentAdmin(string $email = 'fulfillment-admin@test.com'): User
    {
        $u = User::factory()->create(['email' => $email]);
        $u->assignRole('fulfillment_admin');
        return $u;
    }

    private function showWithOrder(array $attrs = []): Show
    {
        $show = Show::create(array_merge([
            'title'      => 'Test Show',
            'show_date'  => now()->toDateString(),
            'status'     => 'reconciled',
            'created_by' => $this->creator->id,
        ], $attrs));

        WhatnotShowOrder::create([
            'show_id' => $show->id, 'buyer_username' => 'buyer1', 'item_name' => 'Card',
            'quantity' => 1, 'total_price' => 10, 'status' => 'completed',
            'show_date' => $show->show_date,
        ]);

        return $show;
    }

    // ── Role helper ────────────────────────────────────────────────────────────

    public function test_is_fulfillment_true_for_fulfillment_role_only(): void
    {
        $fulfillment = $this->fulfillmentUser();
        $this->assertTrue($fulfillment->isFulfillment());

        $admin = $this->admin();
        $admin->assignRole('fulfillment');
        // Admin takes precedence — isFulfillment() excludes admins, mirroring isStreamer().
        $this->assertFalse($admin->isFulfillment());

        $plain = User::factory()->create();
        $this->assertFalse($plain->isFulfillment());
    }

    public function test_is_fulfillment_admin_true_for_fulfillment_admin_role_only(): void
    {
        $fulfillmentAdmin = $this->fulfillmentAdmin();
        $this->assertTrue($fulfillmentAdmin->isFulfillmentAdmin());
        $this->assertFalse($fulfillmentAdmin->isFulfillment());

        $admin = $this->admin();
        $admin->assignRole('fulfillment_admin');
        // Admin takes precedence — isFulfillmentAdmin() excludes admins, same as isFulfillment()/isStreamer().
        $this->assertFalse($admin->isFulfillmentAdmin());

        $plain = User::factory()->create();
        $this->assertFalse($plain->isFulfillmentAdmin());
    }

    public function test_can_switch_channels_true_for_admin_and_fulfillment_admin_only(): void
    {
        $this->assertTrue($this->admin()->canSwitchChannels());
        $this->assertTrue($this->fulfillmentAdmin()->canSwitchChannels());
        $this->assertFalse($this->fulfillmentUser()->canSwitchChannels());

        $streamerUser = User::factory()->create();
        $streamerUser->assignRole('streamer');
        $this->assertFalse($streamerUser->canSwitchChannels());
    }

    // ── Show <-> fulfillment user assignment ────────────────────────────────────

    public function test_show_can_have_fulfillment_users_assigned(): void
    {
        $show = $this->showWithOrder();
        $fulfillment = $this->fulfillmentUser();

        $show->fulfillmentUsers()->attach($fulfillment->id);

        $this->assertTrue($show->fresh()->fulfillmentUsers->contains($fulfillment));
        $this->assertTrue($fulfillment->assignedFulfillmentShows->contains($show));
    }

    // ── Access control ───────────────────────────────────────────────────────

    public function test_canAccess_allows_admin_owner_and_fulfillment_denies_streamer(): void
    {
        $this->actingAs($this->admin());
        $this->assertTrue(FulfillmentResource::canAccess());

        $this->actingAs($this->fulfillmentUser());
        $this->assertTrue(FulfillmentResource::canAccess());

        $streamerRole = Streamer::create(['name' => 'S', 'status' => 'active']);
        $streamerUser = User::factory()->create();
        $streamerUser->assignRole('streamer');
        $streamerRole->update(['user_id' => $streamerUser->id]);
        $this->actingAs($streamerUser);
        $this->assertFalse(FulfillmentResource::canAccess());
    }

    public function test_resource_never_allows_create_edit_or_delete_of_shows(): void
    {
        $show = $this->showWithOrder();
        $this->assertFalse(FulfillmentResource::canCreate());
        $this->assertFalse(FulfillmentResource::canEdit($show));
        $this->assertFalse(FulfillmentResource::canDeleteAny());
    }

    // ── Scoping ──────────────────────────────────────────────────────────────

    public function test_fulfillment_user_sees_only_assigned_shows(): void
    {
        $assigned   = $this->showWithOrder(['title' => 'Assigned']);
        $unassigned = $this->showWithOrder(['title' => 'Unassigned']);

        $fulfillment = $this->fulfillmentUser();
        $assigned->fulfillmentUsers()->attach($fulfillment->id);

        $this->actingAs($fulfillment);
        $ids = FulfillmentResource::getEloquentQuery()->pluck('id')->all();

        $this->assertContains($assigned->id, $ids);
        $this->assertNotContains($unassigned->id, $ids);
    }

    public function test_admin_sees_all_eligible_shows_regardless_of_assignment(): void
    {
        $showA = $this->showWithOrder(['title' => 'A']);
        $showB = $this->showWithOrder(['title' => 'B']);

        $this->actingAs($this->admin());
        $ids = FulfillmentResource::getEloquentQuery()->pluck('id')->all();

        $this->assertContains($showA->id, $ids);
        $this->assertContains($showB->id, $ids);
    }

    public function test_fulfillment_admin_sees_all_eligible_shows_regardless_of_assignment(): void
    {
        $showA = $this->showWithOrder(['title' => 'A']);
        $showB = $this->showWithOrder(['title' => 'B']);

        $this->actingAs($this->fulfillmentAdmin());
        $ids = FulfillmentResource::getEloquentQuery()->pluck('id')->all();

        $this->assertContains($showA->id, $ids);
        $this->assertContains($showB->id, $ids);
        $this->assertTrue(FulfillmentResource::canAccess());
    }

    public function test_channel_scoping_filters_fulfillment_center_for_admin(): void
    {
        $breaks   = WhatnotChannel::create(['name' => 'Vortex Breaks', 'status' => 'active']);
        $collects = WhatnotChannel::create(['name' => 'Vortex Collects', 'status' => 'active']);

        $showBreaks   = $this->showWithOrder(['title' => 'Breaks Show', 'whatnot_channel_id' => $breaks->id]);
        $showCollects = $this->showWithOrder(['title' => 'Collects Show', 'whatnot_channel_id' => $collects->id]);

        $this->actingAs($this->admin());

        // Unscoped ("All Channels") — sees both.
        $ids = FulfillmentResource::getEloquentQuery()->pluck('id')->all();
        $this->assertContains($showBreaks->id, $ids);
        $this->assertContains($showCollects->id, $ids);

        // Scoped to one channel — only that channel's shows.
        ChannelContext::setActive($breaks->id);
        $ids = FulfillmentResource::getEloquentQuery()->pluck('id')->all();
        $this->assertContains($showBreaks->id, $ids);
        $this->assertNotContains($showCollects->id, $ids);
    }

    public function test_draft_cancelled_and_orderless_shows_are_excluded(): void
    {
        $draft     = $this->showWithOrder(['status' => 'draft']);
        $cancelled = $this->showWithOrder(['status' => 'cancelled']);
        $noOrders  = Show::create(['title' => 'No Orders', 'show_date' => now()->toDateString(), 'status' => 'reconciled', 'created_by' => $this->creator->id]);

        $this->actingAs($this->admin());
        $ids = FulfillmentResource::getEloquentQuery()->pluck('id')->all();

        $this->assertNotContains($draft->id, $ids);
        $this->assertNotContains($cancelled->id, $ids);
        $this->assertNotContains($noOrders->id, $ids);
    }

    // ── List/View pages render ──────────────────────────────────────────────

    public function test_list_page_renders_for_fulfillment_user(): void
    {
        $show = $this->showWithOrder();
        $fulfillment = $this->fulfillmentUser();
        $show->fulfillmentUsers()->attach($fulfillment->id);

        Livewire::actingAs($fulfillment);

        Livewire::test(ListFulfillmentShows::class)
            ->assertOk()
            ->assertSee('Test Show');
    }

    public function test_view_page_renders(): void
    {
        $show = $this->showWithOrder();
        $fulfillment = $this->fulfillmentUser();
        $show->fulfillmentUsers()->attach($fulfillment->id);

        Livewire::actingAs($fulfillment);

        Livewire::test(ViewFulfillmentShow::class, ['record' => $show->getRouteKey()])
            ->assertOk()
            ->assertSee('Test Show');
    }

    public function test_orders_relation_manager_shows_items_to_fulfill(): void
    {
        $show = $this->showWithOrder();
        $fulfillment = $this->fulfillmentUser();
        $show->fulfillmentUsers()->attach($fulfillment->id);

        Livewire::actingAs($fulfillment);

        Livewire::test(FulfillmentOrdersRelationManager::class, [
            'ownerRecord' => $show,
            'pageClass'   => ViewFulfillmentShow::class,
        ])
            ->assertOk()
            ->assertSee('Card');
    }

    // ── PWE / label count + fulfillment review actions ──────────────────────

    public function test_update_counts_action_updates_the_streamer_log_entry(): void
    {
        $streamer = Streamer::create(['name' => 'Pwe Streamer', 'status' => 'active', 'payout_type' => 'pwe_labels']);
        $show = $this->showWithOrder();
        $show->streamers()->attach($streamer->id, ['is_primary' => true]);
        $log = StreamerLogEntry::create(['show_id' => $show->id, 'streamer_id' => $streamer->id, 'status' => 'admin_approved']);

        $admin = $this->admin();
        Livewire::actingAs($admin);

        Livewire::test(ViewFulfillmentShow::class, ['record' => $show->getRouteKey()])
            ->callAction('update_counts', data: ['pwe_count' => 12, 'label_count' => 8]);

        $log->refresh();
        $this->assertEquals(12, $log->pwe_count);
        $this->assertEquals(8, $log->label_count);
    }

    public function test_mark_fulfillment_reviewed_action_visible_for_fulfillment_role(): void
    {
        $streamer = Streamer::create(['name' => 'Pwe Streamer', 'status' => 'active', 'payout_type' => 'pwe_labels']);
        $show = $this->showWithOrder();
        $show->streamers()->attach($streamer->id, ['is_primary' => true]);
        StreamerLogEntry::create(['show_id' => $show->id, 'streamer_id' => $streamer->id, 'status' => 'admin_approved']);

        $fulfillment = $this->fulfillmentUser();
        $show->fulfillmentUsers()->attach($fulfillment->id);

        Livewire::actingAs($fulfillment);

        Livewire::test(ViewFulfillmentShow::class, ['record' => $show->getRouteKey()])
            ->assertActionVisible('mark_fulfillment_reviewed')
            ->callAction('mark_fulfillment_reviewed');

        $log = StreamerLogEntry::where('show_id', $show->id)->first();
        $this->assertNotNull($log->fulfillment_reviewed_at);
        $this->assertEquals($fulfillment->id, $log->fulfillment_reviewed_by);
    }

    // ── Shipping status: label_created + bulk actions ────────────────────────

    public function test_label_created_is_a_valid_shipping_status(): void
    {
        $this->assertArrayHasKey('label_created', WhatnotShowOrder::shippingStatusLabels());
    }

    public function test_bulk_mark_action_updates_shipping_status_on_selected_orders(): void
    {
        $show = $this->showWithOrder();
        $order = $show->orders()->first();
        $fulfillment = $this->fulfillmentUser();
        $show->fulfillmentUsers()->attach($fulfillment->id);

        Livewire::actingAs($fulfillment);

        Livewire::test(FulfillmentOrdersRelationManager::class, [
            'ownerRecord' => $show,
            'pageClass'   => ViewFulfillmentShow::class,
        ])
            ->callTableBulkAction('mark_label_created', [$order->id]);

        $this->assertSame('label_created', $order->fresh()->shipping_status);
    }

    // ── Sync status widget ───────────────────────────────────────────────────

    public function test_list_page_shows_sync_status_widget(): void
    {
        Setting::set('whatnot_last_import_success_at', now()->subMinutes(5)->toISOString());
        Livewire::actingAs($this->admin());

        Livewire::test(ListFulfillmentShows::class)
            ->assertOk()
            ->assertSee('Whatnot Order Data')
            ->assertSee('Up to date');
    }

    public function test_list_page_flags_stale_sync(): void
    {
        Setting::set('whatnot_last_import_success_at', now()->subHours(3)->toISOString());
        Livewire::actingAs($this->admin());

        Livewire::test(ListFulfillmentShows::class)
            ->assertOk()
            ->assertSee('Running later than usual');
    }
}
