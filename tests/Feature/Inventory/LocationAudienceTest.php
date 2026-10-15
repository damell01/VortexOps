<?php

namespace Tests\Feature\Inventory;

use App\Filament\Resources\InventoryLocationResource;
use App\Filament\Resources\InventoryLocationResource\Pages\CreateInventoryLocation;
use App\Filament\Resources\InventoryLocationResource\Pages\ListInventoryLocations;
use App\Models\Business;
use App\Models\InventoryLocation;
use App\Models\Streamer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Who sells from a location: everyone, one business, or one streamer. A
 * business with storage of its own keeps its streamers off the shared Main
 * Storage.
 */
class LocationAudienceTest extends TestCase
{
    use RefreshDatabase;

    private Business $vcx;
    private Business $estill;
    private InventoryLocation $shared;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableAdminModules();
        $this->actingAs(User::factory()->create(['email' => config('app.owner_email')]));
        $this->vcx = Business::where('short_name', 'VCX')->firstOrFail();
        $this->estill = Business::where('short_name', 'Estill')->firstOrFail();
        $this->shared = InventoryLocation::create(['name' => 'Main Storage', 'type' => 'main_storage', 'status' => 'active']);
    }

    private function streamer(string $name, Business $business): Streamer
    {
        return Streamer::create(['name' => $name, 'status' => 'active', 'member_type' => 'streamer', 'business_id' => $business->id]);
    }

    private function locationsOf(Streamer $streamer): array
    {
        return $streamer->inventoryLocations()->orderBy('name')->pluck('name')->all();
    }

    public function test_without_storage_of_its_own_a_business_shares_main_storage(): void
    {
        $jay = $this->streamer('Jay', $this->estill);

        $this->assertSame(['Main Storage'], $this->locationsOf($jay));
    }

    public function test_a_business_with_its_own_storage_sells_only_from_it_and_its_streamers_own(): void
    {
        $jay = $this->streamer('Jay', $this->estill);
        $casey = $this->streamer('Casey', $this->vcx);
        InventoryLocation::create(['name' => 'Estill Storage', 'type' => 'main_storage', 'status' => 'active', 'business_id' => $this->estill->id]);
        InventoryLocation::create(['name' => 'Estill Returns', 'type' => 'returned', 'status' => 'active', 'business_id' => $this->estill->id]);
        InventoryLocation::create(['name' => "Jay's Inventory", 'type' => 'streamer_inventory', 'status' => 'active', 'streamer_id' => $jay->id, 'business_id' => $this->estill->id]);

        $this->assertSame(['Estill Storage', "Jay's Inventory"], $this->locationsOf($jay));
        // The other business keeps the shared storage and never sees Estill's.
        $this->assertSame(['Main Storage'], $this->locationsOf($casey));
    }

    public function test_the_setup_form_saves_who_uses_a_location(): void
    {
        $jay = $this->streamer('Jay', $this->estill);

        Livewire::test(CreateInventoryLocation::class)
            ->fillForm(['name' => 'Jay Shelf', 'type' => 'streamer_inventory', 'audience' => 'streamer', 'streamer_id' => $jay->id])
            ->call('create')->assertHasNoFormErrors();
        Livewire::test(CreateInventoryLocation::class)
            ->fillForm(['name' => 'Estill Back Room', 'type' => 'main_storage', 'audience' => 'business', 'business_id' => $this->estill->id])
            ->call('create')->assertHasNoFormErrors();

        $shelf = InventoryLocation::where('name', 'Jay Shelf')->first();
        $this->assertSame($jay->id, $shelf->streamer_id);
        $this->assertSame($this->estill->id, $shelf->business_id);
        $room = InventoryLocation::where('name', 'Estill Back Room')->first();
        $this->assertNull($room->streamer_id);
        $this->assertSame($this->estill->id, $room->business_id);
        $this->assertSame('Estill Distribution', $room->audienceLabel());
    }

    public function test_the_shortcuts_set_up_a_streamer_or_a_business_in_one_step(): void
    {
        $jay = $this->streamer('Jay', $this->estill);

        Livewire::test(ListInventoryLocations::class)
            ->callAction('streamerLocation', ['streamer_id' => $jay->id, 'name' => "Jay's Inventory"])
            ->callAction('businessStorage', ['business_id' => $this->estill->id, 'name' => 'Estill Storage']);

        $this->assertSame('streamer_inventory', InventoryLocation::where('name', "Jay's Inventory")->value('type'));
        $this->assertSame($this->estill->id, InventoryLocation::where('name', 'Estill Storage')->value('business_id'));
        $this->assertSame(['Estill Storage', "Jay's Inventory"], $this->locationsOf($jay->fresh()));
    }

    public function test_applying_everyone_clears_any_owner(): void
    {
        $data = InventoryLocationResource::applyAudience(['name' => 'X', 'audience' => 'everyone', 'streamer_id' => 5, 'business_id' => 2]);

        $this->assertNull($data['streamer_id']);
        $this->assertNull($data['business_id']);
        $this->assertArrayNotHasKey('audience', $data);
    }
}
