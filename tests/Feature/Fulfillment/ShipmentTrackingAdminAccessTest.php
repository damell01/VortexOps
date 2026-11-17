<?php

namespace Tests\Feature\Fulfillment;

use App\Filament\Pages\ShipmentOverview;
use App\Filament\Resources\FulfillmentResource;
use App\Http\Controllers\FulfillmentLabelController;
use App\Models\Setting;
use App\Models\Shipment;
use App\Models\Show;
use App\Models\User;
use App\Models\WhatnotChannel;
use App\Support\AdminModules;
use App\Support\ChannelContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ShipmentTrackingAdminAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['admin', 'super_admin', 'fulfillment_admin', 'fulfillment', 'streamer'] as $role) {
            Role::findOrCreate($role, 'web');
        }
        Setting::set('enabled_admin_modules', json_encode(['streams', 'fulfillment']));
        AdminModules::flushMemo();
        ChannelContext::setActive(null);
    }

    protected function tearDown(): void
    {
        FulfillmentLabelController::$resolve = null;
        ChannelContext::flushMemo();
        AdminModules::flushMemo();
        parent::tearDown();
    }

    private function shipment(string $buyer = 'buyer_one', ?int $channelId = null): Shipment
    {
        // Deliberately no approved streamer report: these shipments are
        // imported but not eligible for FulfillmentResource's packing queue.
        $show = Show::create([
            'title' => 'Shipment tracking only ' . $buyer,
            'show_date' => today()->subDay()->toDateString(),
            'status' => 'reconciled',
            'is_operational' => true,
            'whatnot_channel_id' => $channelId,
        ]);

        return Shipment::create([
            'show_id' => $show->id,
            'buyer_username' => $buyer,
            'status' => 'in_transit',
            'tracking_number' => 'TRACK-' . $buyer,
            'raw_payload' => ['documents' => ['label_url' => 'https://labels.whatnot.test/' . $buyer . '.pdf']],
        ]);
    }

    public function test_owner_admin_super_admin_and_fulfillment_admin_can_view_imported_shipment_details_before_report_approval(): void
    {
        $shipment = $this->shipment();
        $this->assertFalse(FulfillmentResource::getEloquentQuery()->whereKey($shipment->show_id)->exists());

        foreach (['owner', 'admin', 'super_admin', 'fulfillment_admin'] as $role) {
            $user = User::factory()->create($role === 'owner'
                ? ['email' => config('app.owner_email') ?: 'dbellcreations@gmail.com']
                : []);
            if ($role !== 'owner') $user->assignRole($role);

            $this->actingAs($user)->get(ShipmentOverview::getUrl(panel: 'admin'))->assertOk();
            $page = Livewire::actingAs($user)->test(ShipmentOverview::class)
                ->assertSee('buyer_one')
                ->call('selectShipment', $shipment->id)
                ->assertSet('selectedShipmentId', $shipment->id)
                ->assertSee('TRACK-buyer_one')
                ->assertDontSee('Show shipping workspace');

            $this->assertSame($shipment->id, $page->instance()->selectedShipment()?->id);
            $this->assertSame(0, $page->instance()->shipmentHistory($shipment->id)->count());
            $this->assertTrue($page->instance()->recentShows()->contains('id', $shipment->show_id));
        }
    }

    public function test_non_admin_accounts_cannot_open_tracking_or_preview_labels(): void
    {
        $shipment = $this->shipment();
        foreach (['fulfillment', 'streamer', null] as $role) {
            $user = User::factory()->create();
            if ($role) $user->assignRole($role);
            $this->actingAs($user);
            $this->assertFalse(ShipmentOverview::canAccess());
            $this->get(ShipmentOverview::getUrl(panel: 'admin'))->assertForbidden();
            $this->get(route('fulfillment.shipment-label', ['shipment' => $shipment->id]))->assertNotFound();
        }
    }

    public function test_admin_can_preview_imported_label_before_packing_approval(): void
    {
        $shipment = $this->shipment();
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Http::fake(['labels.whatnot.test/*' => Http::response('%PDF-1.4 label', 200, ['Content-Type' => 'application/pdf'])]);
        FulfillmentLabelController::$resolve = fn () => ['93.184.216.34'];

        $this->actingAs($admin)
            ->get(route('fulfillment.shipment-label', ['shipment' => $shipment->id]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_admin_tracking_and_label_access_follow_active_channel(): void
    {
        $channelA = WhatnotChannel::create(['name' => 'A', 'whatnot_username' => 'channel_a', 'status' => 'active']);
        $channelB = WhatnotChannel::create(['name' => 'B', 'whatnot_username' => 'channel_b', 'status' => 'active']);
        $a = $this->shipment('buyer_a', $channelA->id);
        $b = $this->shipment('buyer_b', $channelB->id);

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin);
        ChannelContext::setActive($channelA->id);

        $page = Livewire::actingAs($admin)->test(ShipmentOverview::class)
            ->assertSee('buyer_a')->assertDontSee('buyer_b');
        $this->assertSame([$a->id], $page->instance()->dashboardData()['rows']->pluck('id')->all());
        $this->assertTrue($page->instance()->recentShows()->contains('id', $a->show_id));
        $this->assertFalse($page->instance()->recentShows()->contains('id', $b->show_id));

        $page->call('selectShipment', $b->id)->assertStatus(404);
        $this->get(route('fulfillment.shipment-label', ['shipment' => $b->id]))->assertNotFound();
    }
}
