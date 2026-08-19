<?php

namespace Tests\Feature\Receiving;

use App\Filament\Resources\PalletResource\Pages\ViewPallet;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\Pallet;
use App\Models\PalletLine;
use App\Models\User;
use App\Models\Vendor;
use App\Services\ReceivingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Staging a pallet from the paperwork, before the products exist.
 *
 * A pallet is written down from what the vendor sent, and most of it is not in
 * inventory yet. Requiring an existing product to add a line meant creating
 * every product first, which is the opposite order to how the job is done —
 * and doing it from a packing slip, for boxes nobody has looked at. So a line
 * needs a name and nothing else, and the barcode settles what it really is
 * when the box is in hand.
 */
class StageByNameTest extends TestCase
{
    use RefreshDatabase;

    private Pallet $pallet;
    private InventoryLocation $location;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableAdminModules();

        $this->actingAs(
            (User::firstWhere('email', config('app.owner_email'))
                ?? User::factory()->create(['email' => config('app.owner_email')]))->fresh()
        );

        $this->location = InventoryLocation::create(['name' => 'Main', 'type' => 'main_storage', 'status' => 'active']);

        $this->pallet = Pallet::create([
            'vendor_id' => Vendor::create(['name' => 'V', 'status' => 'active'])->id,
            'reference' => 'PO-STAGE',
            'status'    => 'staged',
        ]);
    }

    private function page()
    {
        return Livewire::test(ViewPallet::class, ['record' => $this->pallet->id]);
    }

    public function test_a_name_alone_is_enough_to_stage_an_item(): void
    {
        $this->page()->callAction('add_expected_item', [
            'name' => '2026 Topps Chrome Hobby',
        ]);

        $line = $this->pallet->lines()->firstOrFail();

        $this->assertSame('2026 Topps Chrome Hobby', $line->description);
        $this->assertNull($line->inventory_item_id, 'It should stage without inventing a product.');
        $this->assertNull($line->inventory_location_id);
        $this->assertSame(1, (int) $line->case_count);
    }

    public function test_case_or_single_is_recorded_when_known_and_left_open_when_not(): void
    {
        // Worth capturing at staging because it is the moment somebody is
        // actually looking at the thing, and it decides what the product
        // becomes later.
        $this->page()->callAction('add_expected_item', ['name' => 'A case', 'form_factor' => 'container']);
        $this->page()->callAction('add_expected_item', ['name' => 'A single', 'form_factor' => 'single']);
        $this->page()->callAction('add_expected_item', ['name' => 'No idea', 'form_factor' => 'unknown']);

        $lines = $this->pallet->lines()->orderBy('line_number')->get();

        $this->assertTrue($lines[0]->is_container);
        $this->assertFalse($lines[1]->is_container);
        $this->assertNull($lines[2]->is_container, 'Unknown is a real answer, not "not a case".');
    }

    public function test_an_existing_item_can_still_be_linked_up_front(): void
    {
        $item = InventoryItem::create(['name' => 'Known Box', 'sku' => 'KB-1', 'is_active' => true]);

        $this->page()->callAction('add_expected_item', [
            'name'                  => 'Known Box',
            'inventory_item_id'     => $item->id,
            'inventory_location_id' => $this->location->id,
            'case_count'            => 3,
        ]);

        $line = $this->pallet->lines()->firstOrFail();

        $this->assertSame($item->id, $line->inventory_item_id);
        $this->assertSame(3, (int) $line->case_count);
    }

    public function test_scanning_an_unknown_code_creates_the_item_and_links_it(): void
    {
        $line = PalletLine::create([
            'pallet_id'    => $this->pallet->id,
            'line_number'  => 1,
            'description'  => 'Mystery Case',
            'is_container' => true,
            'case_count'   => 2,
            'unit_cost'    => 40,
        ]);

        $result = app(ReceivingService::class)->linkLineByScan($line, '999888777', $this->location);

        $this->assertTrue($result['created']);
        $this->assertSame('Mystery Case', $result['item']->name);
        $this->assertSame('999888777', $result['item']->barcode);
        $this->assertTrue((bool) $result['item']->is_container, 'What was staged as a case should become one.');

        $line->refresh();
        $this->assertSame($result['item']->id, $line->inventory_item_id);
        $this->assertSame($this->location->id, $line->inventory_location_id);
        $this->assertTrue($line->isFullyMapped());
    }

    public function test_scanning_a_known_code_links_rather_than_duplicating(): void
    {
        $existing = InventoryItem::create(['name' => 'Already Here', 'sku' => 'AH-1', 'barcode' => '5551234', 'is_active' => true]);

        $line = PalletLine::create([
            'pallet_id'   => $this->pallet->id,
            'line_number' => 1,
            'description' => 'Whatever the slip called it',
            'case_count'  => 1,
        ]);

        $before = InventoryItem::count();
        $result = app(ReceivingService::class)->linkLineByScan($line, '5551234', $this->location);

        $this->assertFalse($result['created']);
        $this->assertSame($existing->id, $result['item']->id);
        $this->assertSame($before, InventoryItem::count(), 'A known barcode must not spawn a duplicate.');
    }

    public function test_the_same_item_cannot_be_scanned_onto_two_lines(): void
    {
        // Two lines pointing at one product makes every later scan ambiguous,
        // and the units land on whichever line happens to be found first.
        $item = InventoryItem::create(['name' => 'Dup', 'sku' => 'D-1', 'barcode' => '4242', 'is_active' => true]);

        PalletLine::create([
            'pallet_id'         => $this->pallet->id,
            'line_number'       => 1,
            'description'       => 'Dup',
            'inventory_item_id' => $item->id,
            'case_count'        => 1,
        ]);

        $second = PalletLine::create([
            'pallet_id'   => $this->pallet->id,
            'line_number' => 2,
            'description' => 'Dup again',
            'case_count'  => 1,
        ]);

        $this->expectException(\RuntimeException::class);

        app(ReceivingService::class)->linkLineByScan($second, '4242', $this->location);
    }

    public function test_a_line_that_is_already_linked_is_refused(): void
    {
        $item = InventoryItem::create(['name' => 'Linked', 'sku' => 'L-1', 'is_active' => true]);

        $line = PalletLine::create([
            'pallet_id'         => $this->pallet->id,
            'line_number'       => 1,
            'description'       => 'Linked',
            'inventory_item_id' => $item->id,
            'case_count'        => 1,
        ]);

        $this->expectException(\RuntimeException::class);

        app(ReceivingService::class)->linkLineByScan($line, '111222', $this->location);
    }

    public function test_the_create_form_stages_a_line_from_a_name_alone(): void
    {
        // The other place lines get typed. This repeater demanded cases and
        // units per box on every row, so writing a pallet down was four
        // decisions a line rather than one — most of them unknowable from a
        // slip, and all of them editable afterwards.
        Livewire::test(\App\Filament\Resources\PalletResource\Pages\CreatePallet::class)
            ->fillForm([
                'vendor_id' => $this->pallet->vendor_id,
                'reference' => 'PO-FROM-FORM',
                'status'    => 'staged',
                'lines'     => [
                    ['description' => 'Just a name'],
                    ['description' => 'A case of something', 'is_container' => 1],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $pallet = Pallet::firstWhere('reference', 'PO-FROM-FORM');

        $this->assertNotNull($pallet);
        $this->assertSame(2, $pallet->lines()->count());

        $lines = $pallet->lines()->orderBy('line_number')->get();
        $this->assertSame('Just a name', $lines[0]->description);
        $this->assertNull($lines[0]->inventory_item_id);
        $this->assertTrue((bool) $lines[1]->is_container);
    }

    public function test_the_create_form_can_point_a_line_at_an_existing_item(): void
    {
        // "More stock of something I already have" — the case where the
        // product exists and this pallet is topping it up.
        $item = InventoryItem::create(['name' => 'Restock Me', 'sku' => 'RM-1', 'is_active' => true]);

        Livewire::test(\App\Filament\Resources\PalletResource\Pages\CreatePallet::class)
            ->fillForm([
                'vendor_id' => $this->pallet->vendor_id,
                'reference' => 'PO-RESTOCK',
                'status'    => 'staged',
                'lines'     => [[
                    'description'           => 'Restock Me',
                    'inventory_item_id'     => $item->id,
                    'inventory_location_id' => $this->location->id,
                    'case_count'            => 4,
                ]],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $line = Pallet::firstWhere('reference', 'PO-RESTOCK')->lines()->firstOrFail();

        $this->assertSame($item->id, $line->inventory_item_id);
        $this->assertTrue($line->isFullyMapped(), 'A line pointed at a known item is ready to receive.');
    }

    public function test_a_staged_item_can_be_linked_and_then_received_through_the_page(): void
    {
        // The whole point of staging by name: the round trip has to end with
        // stock on the shelf, not with a line that can never be scanned.
        $this->page()->callAction('add_expected_item', [
            'name'        => 'Prep Box',
            'form_factor' => 'single',
            'case_count'  => 1,
            'unit_cost'   => 25,
        ]);

        $line = $this->pallet->lines()->firstOrFail();

        $this->page()->callAction('link_by_scan', [
            'pallet_line_id'        => $line->id,
            'code'                  => '7778889990',
            'inventory_location_id' => $this->location->id,
        ]);

        $line->refresh();
        $this->assertNotNull($line->inventory_item_id);

        $result = app(ReceivingService::class)->receiveOneCaseByItemCode($this->pallet, '7778889990');

        $this->assertSame(1, $result['received']);
        $this->assertTrue($result['complete']);
    }
}
