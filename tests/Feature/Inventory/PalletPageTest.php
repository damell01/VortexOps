<?php

namespace Tests\Feature\Inventory;

use App\Filament\Resources\PalletResource\Pages\ViewPallet;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\Pallet;
use App\Models\PalletLine;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The pallet's own page is where receiving is actually worked.
 *
 * Photos, paperwork and the costs that decide what the stock is worth were all
 * somewhere else — the edit form, or another page entirely — which meant
 * leaving the pallet mid-job to record something about it. They are on the
 * pallet now.
 */
class PalletPageTest extends TestCase
{
    use RefreshDatabase;

    private Pallet $pallet;
    private InventoryItem $item;
    private InventoryLocation $location;

    protected function setUp(): void
    {
        parent::setUp();

        // Receiving lives in the purchasing module, which the shell-phase
        // migration leaves switched off.
        $this->enableAdminModules();

        $this->actingAs(
            (User::firstWhere('email', config('app.owner_email'))
                ?? User::factory()->create(['email' => config('app.owner_email')]))->fresh()
        );

        $vendor         = Vendor::create(['name' => 'Page Vendor', 'status' => 'active']);
        $this->location = InventoryLocation::create(['name' => 'Main', 'type' => 'main_storage', 'status' => 'active']);
        $this->item     = InventoryItem::create(['name' => 'Box', 'unit_cost' => 10, 'average_cost' => 0, 'is_active' => true]);

        $this->pallet = Pallet::create([
            'vendor_id'     => $vendor->id,
            'reference'     => 'PO-PAGE',
            'status'        => 'pending',
            'shipping_cost' => 30,
            'payment_fees'  => 20,
        ]);

        PalletLine::create([
            'pallet_id'             => $this->pallet->id,
            'line_number'           => 1,
            'description'           => 'Box',
            'inventory_item_id'     => $this->item->id,
            'inventory_location_id' => $this->location->id,
            'case_count'            => 2,
            'quantity_per_case'     => 5,
            'unit_cost'             => 10,
        ]);
    }

    private function page()
    {
        return Livewire::test(ViewPallet::class, ['record' => $this->pallet->id]);
    }

    public function test_the_page_shows_what_the_pallet_will_cost(): void
    {
        // Goods 2 x 5 x $10 = $100, plus $50 of shipping and fees = $150
        // across 10 units, which is $15 a unit once received.
        $this->page()
            ->assertOk()
            ->assertSee('Landed Cost')
            ->assertSee('$150.00')
            ->assertSee('$15.00');
    }

    public function test_costs_can_be_set_from_the_pallet_itself(): void
    {
        $this->page()->callAction('edit_costs', [
            'shipping_cost' => 10,
            'payment_fees'  => 5,
        ]);

        $this->pallet->refresh();

        $this->assertEquals(10.00, (float) $this->pallet->shipping_cost);
        $this->assertEquals(5.00, (float) $this->pallet->payment_fees);
        $this->assertSame(15.0, $this->pallet->landedCostExtras());
    }

    public function test_receiving_from_the_page_works(): void
    {
        // This action called a method that does not exist, so it threw the
        // moment it succeeded — the stock was credited and the page then blew
        // up, which reads as the receive having failed.
        $this->page()->callAction('receive_all');

        $this->pallet->refresh();

        $this->assertSame('received', $this->pallet->status);

        // $10 a unit plus $50 spread over 10 units.
        $this->assertEqualsWithDelta(15.00, (float) $this->item->fresh()->average_cost, 0.0001);
    }

    public function test_mapping_a_line_from_the_page_works(): void
    {
        $line = PalletLine::create([
            'pallet_id'   => $this->pallet->id,
            'line_number' => 2,
            'description' => 'Unmapped line',
            'case_count'  => 1,
            'quantity_per_case' => 1,
            'unit_cost'   => 5,
        ]);

        $this->page()->callAction('map_line', [
            'pallet_line_id'        => $line->id,
            'inventory_item_id'     => $this->item->id,
            'inventory_location_id' => $this->location->id,
        ]);

        $line->refresh();

        $this->assertSame($this->item->id, $line->inventory_item_id);
        $this->assertSame($this->location->id, $line->inventory_location_id);
    }

    public function test_attachments_can_be_added_without_leaving_the_pallet(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');

        $path = 'pallets/evidence.jpg';
        \Illuminate\Support\Facades\Storage::disk('public')->put(
            $path,
            // A one-pixel JPEG, so mime detection sees a real image.
            base64_decode('/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/wAALCAABAAEBAREA/8QAFAABAAAAAAAAAAAAAAAAAAAACf/EABQQAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQEAAD8AKp//2Q==')
        );

        $this->page()->callAction('add_attachments', [
            'files'       => [$path],
            'description' => 'crushed corner',
        ]);

        $this->pallet->refresh();

        $this->assertSame(1, $this->pallet->attachments()->count());
        $this->assertSame('crushed corner', $this->pallet->attachments()->first()->description);
        $this->assertSame(1, (int) $this->pallet->attachments_count);
    }

    public function test_attachments_can_also_be_added_from_the_receiving_page(): void
    {
        // Where the photographs are actually taken. This page listed
        // attachments but could not add any — it said to go and edit the
        // pallet, which is a page change, a form and a walk back, done
        // one-handed while holding a box.
        \Illuminate\Support\Facades\Storage::fake('public');

        $path = 'pallets/damage.jpg';
        \Illuminate\Support\Facades\Storage::disk('public')->put(
            $path,
            base64_decode('/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/wAALCAABAAEBAREA/8QAFAABAAAAAAAAAAAAAAAAAAAACf/EABQQAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQEAAD8AKp//2Q==')
        );

        Livewire::test(
            \App\Filament\Resources\PalletResource\Pages\ReceivePallet::class,
            ['record' => $this->pallet],
        )->callAction('add_attachments', [
            'files'       => [$path],
            'description' => 'seal broken on arrival',
        ]);

        $this->pallet->refresh();

        $this->assertSame(1, $this->pallet->attachments()->count());
        $this->assertSame('seal broken on arrival', $this->pallet->attachments()->first()->description);
    }

    public function test_the_receiving_page_renders(): void
    {
        // It fatalled outright for a while: the view called PalletResource
        // unqualified, and Blade compiles with no imports, so the name
        // resolved to the global namespace.
        Livewire::test(
            \App\Filament\Resources\PalletResource\Pages\ReceivePallet::class,
            ['record' => $this->pallet],
        )->assertOk();
    }
}
