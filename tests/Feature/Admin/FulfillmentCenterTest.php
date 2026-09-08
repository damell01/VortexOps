<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\FulfillmentResource;
use App\Filament\Resources\FulfillmentResource\Pages\ListFulfillmentShows;
use App\Filament\Resources\FulfillmentResource\Pages\ViewFulfillmentShow;
use App\Filament\Resources\FulfillmentResource\RelationManagers\FulfillmentOrdersRelationManager;
use App\Livewire\FulfillmentDashboard;
use App\Models\FulfillmentPackage;
use App\Models\Setting;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\StreamerLogEntry;
use App\Models\StreamerLogItem;
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

    protected function tearDown(): void
    {
        AdminModules::flushMemo();
        ChannelContext::clear();
        parent::tearDown();
    }

    private function admin(): User
    {
        $user = User::factory()->create(['email' => 'admin@test.com']);
        $user->assignRole('admin');
        return $user;
    }

    private function fulfillmentUser(string $email = 'fulfillment@test.com'): User
    {
        $user = User::factory()->create(['email' => $email]);
        $user->assignRole('fulfillment');
        return $user;
    }

    private function fulfillmentAdmin(string $email = 'fulfillment-admin@test.com'): User
    {
        $user = User::factory()->create(['email' => $email]);
        $user->assignRole('fulfillment_admin');
        return $user;
    }

    /** Build the actual Fulfillment input: approved streamer report + item lines. */
    private function fulfillmentReadyShow(array $attrs = [], bool $approved = true): Show
    {
        $show = Show::create(array_merge([
            'title' => 'Test Show',
            'show_date' => now()->toDateString(),
            'status' => 'reconciled',
            'created_by' => $this->creator->id,
        ], $attrs));

        $streamer = Streamer::create(['name' => 'Streamer ' . $show->id, 'status' => 'active']);
        $show->streamers()->attach($streamer->id, ['is_primary' => true]);

        $log = StreamerLogEntry::create([
            'show_id' => $show->id,
            'streamer_id' => $streamer->id,
            'status' => $approved ? 'admin_approved' : 'streamer_reviewed',
            'approval_status' => $approved ? 'approved' : 'pending_approval',
            'submitted_at' => now()->subMinute(),
            'streamer_reviewed_at' => now()->subMinute(),
        ]);

        StreamerLogItem::create([
            'streamer_log_entry_id' => $log->id,
            'item_name' => 'Card',
            'quantity' => 3,
            'packed_quantity' => 0,
            'unit_cost' => 10,
            'disposition' => 'sold',
            'fulfillment_status' => StreamerLogItem::FULFILLMENT_PENDING,
        ]);

        WhatnotShowOrder::create([
            'show_id' => $show->id,
            'buyer_username' => 'buyer1',
            'item_name' => 'Card',
            'quantity' => 1,
            'total_price' => 10,
            'status' => 'completed',
            'show_date' => $show->show_date,
        ]);

        return $show->fresh(['streamers', 'streamerLogEntry.items', 'fulfillmentUsers']);
    }

    public function test_is_fulfillment_true_for_fulfillment_role_only(): void
    {
        $fulfillment = $this->fulfillmentUser();
        $this->assertTrue($fulfillment->isFulfillment());
        $admin = $this->admin();
        $admin->assignRole('fulfillment');
        $this->assertFalse($admin->isFulfillment());
        $this->assertFalse(User::factory()->create()->isFulfillment());
    }

    public function test_is_fulfillment_admin_true_for_fulfillment_admin_role_only(): void
    {
        $fulfillmentAdmin = $this->fulfillmentAdmin();
        $this->assertTrue($fulfillmentAdmin->isFulfillmentAdmin());
        $this->assertFalse($fulfillmentAdmin->isFulfillment());
        $admin = $this->admin();
        $admin->assignRole('fulfillment_admin');
        $this->assertFalse($admin->isFulfillmentAdmin());
    }

    public function test_can_switch_channels_true_for_admin_and_fulfillment_admin_only(): void
    {
        $this->assertTrue($this->admin()->canSwitchChannels());
        $this->assertTrue($this->fulfillmentAdmin()->canSwitchChannels());
        $this->assertFalse($this->fulfillmentUser()->canSwitchChannels());
    }

    public function test_show_can_have_fulfillment_users_assigned(): void
    {
        $show = $this->fulfillmentReadyShow();
        $fulfillment = $this->fulfillmentUser();
        $show->fulfillmentUsers()->attach($fulfillment->id);
        $this->assertTrue($show->fresh()->fulfillmentUsers->contains($fulfillment));
        $this->assertTrue($fulfillment->assignedFulfillmentShows->contains($show));
    }

    public function test_resource_access_allows_ops_roles_and_denies_streamer(): void
    {
        $this->actingAs($this->admin());
        $this->assertTrue(FulfillmentResource::canAccess());
        $this->actingAs($this->fulfillmentUser());
        $this->assertTrue(FulfillmentResource::canAccess());

        $streamer = Streamer::create(['name' => 'S', 'status' => 'active']);
        $streamerUser = User::factory()->create();
        $streamerUser->assignRole('streamer');
        $streamer->update(['user_id' => $streamerUser->id]);
        $this->actingAs($streamerUser);
        $this->assertFalse(FulfillmentResource::canAccess());
    }

    public function test_resource_never_allows_create_edit_or_delete_of_shows(): void
    {
        $show = $this->fulfillmentReadyShow();
        $this->assertFalse(FulfillmentResource::canCreate());
        $this->assertFalse(FulfillmentResource::canEdit($show));
        $this->assertFalse(FulfillmentResource::canDeleteAny());
    }

    public function test_fulfillment_user_sees_only_approved_work_assigned_to_them(): void
    {
        $assigned = $this->fulfillmentReadyShow(['title' => 'Assigned']);
        $other = $this->fulfillmentReadyShow(['title' => 'Someone Else']);
        $notApproved = $this->fulfillmentReadyShow(['title' => 'Waiting on Admin'], approved: false);
        $fulfillment = $this->fulfillmentUser();
        $someoneElse = $this->fulfillmentUser('other@test.com');
        $assigned->fulfillmentUsers()->attach($fulfillment->id);
        $other->fulfillmentUsers()->attach($someoneElse->id);
        $notApproved->fulfillmentUsers()->attach($fulfillment->id);

        $this->actingAs($fulfillment);
        $ids = FulfillmentResource::getEloquentQuery()->pluck('id')->all();
        $this->assertContains($assigned->id, $ids);
        $this->assertNotContains($other->id, $ids);
        $this->assertNotContains($notApproved->id, $ids);
    }

    public function test_admin_and_fulfillment_admin_can_see_unassigned_approved_work(): void
    {
        $showA = $this->fulfillmentReadyShow(['title' => 'A']);
        $showB = $this->fulfillmentReadyShow(['title' => 'B']);

        $this->actingAs($this->admin());
        $ids = FulfillmentResource::getEloquentQuery()->pluck('id')->all();
        $this->assertContains($showA->id, $ids);
        $this->assertContains($showB->id, $ids);

        $this->actingAs($this->fulfillmentAdmin());
        $ids = FulfillmentResource::getEloquentQuery()->pluck('id')->all();
        $this->assertContains($showA->id, $ids);
        $this->assertContains($showB->id, $ids);
    }

    public function test_fulfillment_user_cannot_open_unassigned_show_directly(): void
    {
        $show = $this->fulfillmentReadyShow();
        $this->actingAs($this->fulfillmentUser());
        $this->assertFalse(FulfillmentResource::canView($show));
    }

    public function test_historical_show_is_excluded_even_when_it_has_an_approved_report(): void
    {
        $show = $this->fulfillmentReadyShow();
        $show->is_operational = false;
        $show->save();

        $this->actingAs($this->admin());
        $this->assertNotContains($show->id, FulfillmentResource::getEloquentQuery()->pluck('id')->all());
    }

    public function test_order_data_alone_does_not_create_fulfillment_work(): void
    {
        $show = Show::create([
            'title' => 'Imported Only',
            'show_date' => now()->toDateString(),
            'status' => 'reconciled',
            'created_by' => $this->creator->id,
        ]);
        WhatnotShowOrder::create([
            'show_id' => $show->id,
            'buyer_username' => 'buyer1',
            'item_name' => 'Card',
            'quantity' => 4,
            'total_price' => 40,
            'status' => 'completed',
            'show_date' => $show->show_date,
        ]);

        $this->actingAs($this->admin());
        $this->assertNotContains($show->id, FulfillmentResource::getEloquentQuery()->pluck('id')->all());
    }

    public function test_channel_scoping_still_applies_to_active_fulfillment(): void
    {
        $breaks = WhatnotChannel::create(['name' => 'Vortex Breaks', 'status' => 'active']);
        $collects = WhatnotChannel::create(['name' => 'Vortex Collects', 'status' => 'active']);
        $showBreaks = $this->fulfillmentReadyShow(['title' => 'Breaks Show', 'whatnot_channel_id' => $breaks->id]);
        $showCollects = $this->fulfillmentReadyShow(['title' => 'Collects Show', 'whatnot_channel_id' => $collects->id]);

        $this->actingAs($this->admin());
        ChannelContext::setActive($breaks->id);
        $ids = FulfillmentResource::getEloquentQuery()->pluck('id')->all();
        $this->assertContains($showBreaks->id, $ids);
        $this->assertNotContains($showCollects->id, $ids);
    }

    public function test_list_page_renders_assigned_work(): void
    {
        $show = $this->fulfillmentReadyShow();
        $fulfillment = $this->fulfillmentUser();
        $show->fulfillmentUsers()->attach($fulfillment->id);

        Livewire::actingAs($fulfillment);
        Livewire::test(ListFulfillmentShows::class)->assertOk()->assertSee('Test Show');
    }

    public function test_view_page_renders_for_assigned_fulfillment_member(): void
    {
        $show = $this->fulfillmentReadyShow();
        $fulfillment = $this->fulfillmentUser();
        $show->fulfillmentUsers()->attach($fulfillment->id);

        Livewire::actingAs($fulfillment);
        Livewire::test(ViewFulfillmentShow::class, ['record' => $show->getRouteKey()])
            ->assertOk()->assertSee('Test Show');
    }

    public function test_items_can_be_packed_and_completed_without_creating_a_box(): void
    {
        $show = $this->fulfillmentReadyShow();
        $fulfillment = $this->fulfillmentUser();
        $show->fulfillmentUsers()->attach($fulfillment->id);
        $line = $show->streamerLogEntry->items->first();

        Livewire::actingAs($fulfillment);
        Livewire::test(FulfillmentDashboard::class, ['show' => $show])
            ->call('packRemaining', $line->id)
            ->call('completeFulfillment');

        $this->assertSame(3, (int) $line->fresh()->packed_quantity);
        $this->assertNotNull($show->fresh()->streamerLogEntry->fulfillment_reviewed_at);
        $this->assertSame(0, FulfillmentPackage::where('show_id', $show->id)->count());
    }

    public function test_order_reference_tools_still_work_as_optional_secondary_tools(): void
    {
        $show = $this->fulfillmentReadyShow();
        $fulfillment = $this->fulfillmentUser();
        $show->fulfillmentUsers()->attach($fulfillment->id);

        Livewire::actingAs($fulfillment);
        Livewire::test(FulfillmentOrdersRelationManager::class, [
            'ownerRecord' => $show,
            'pageClass' => ViewFulfillmentShow::class,
        ])->assertOk()->assertSee('Card');
    }

    public function test_label_created_is_still_a_valid_optional_shipping_status(): void
    {
        $this->assertArrayHasKey('label_created', WhatnotShowOrder::shippingStatusLabels());
    }

    public function test_bulk_shipping_reference_action_still_updates_selected_order(): void
    {
        $show = $this->fulfillmentReadyShow();
        $order = $show->orders()->first();
        $fulfillment = $this->fulfillmentUser();
        $show->fulfillmentUsers()->attach($fulfillment->id);

        Livewire::actingAs($fulfillment);
        Livewire::test(FulfillmentOrdersRelationManager::class, [
            'ownerRecord' => $show,
            'pageClass' => ViewFulfillmentShow::class,
        ])->callTableBulkAction('mark_label_created', [$order->id]);

        $this->assertSame('label_created', $order->fresh()->shipping_status);
    }
}
