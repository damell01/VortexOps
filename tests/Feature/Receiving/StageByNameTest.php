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

    /**
     * Stage a line the way the app now does it — through the manifest table.
     *
     * These tests used to drive an "Add Expected Item" modal on the pallet page.
     * That did the same job as the table and was removed rather than kept
     * alongside it, so the tests follow the capability instead of the button.
     *
     * @param  array<int, array<string, mixed>>  $lines
     */
    private function stage(array $lines): void
    {
        $page = Livewire::test(
            \App\Filament\Resources\PalletResource\Pages\AddPalletLines::class,
            ['record' => $this->pallet->id],
        );

        // The page opens on the manifest as it stands, so a second call has to
        // type into the blank rows underneath rather than over the lines the
        // first one added. The modal always appended; this keeps that.
        $existing = $page->get('rows');
        $offset   = count(array_filter(
            $existing,
            fn ($row) => trim((string) ($row['description'] ?? '')) !== '',
        ));
        $available = count($existing);

        foreach (array_values($lines) as $n => $line) {
            $i = $offset + $n;

            while ($available <= $i) {
                $page->call('addRow');
                $available++;
            }

            // The modal's vocabulary, translated to the table's. Keeping the
            // callers unchanged keeps these tests about staging rather than
            // about which screen happens to do it this month.
            $row = [
                'description'       => $line['name'] ?? $line['description'] ?? '',
                'is_container'      => match ($line['form_factor'] ?? null) {
                    'container' => '1',
                    'single'    => '0',
                    'unknown'   => '',
                    default     => $line['is_container'] ?? '',
                },
                'case_count'        => $line['case_count'] ?? 1,
                'quantity_per_case' => $line['quantity_per_case'] ?? 1,
                'inventory_item_id' => isset($line['inventory_item_id']) ? (string) $line['inventory_item_id'] : '',
            ];

            foreach ($row as $field => $value) {
                $page->set("rows.{$i}.{$field}", $value);
            }

            if (isset($line['inventory_location_id'])) {
                $page->set('locationId', $line['inventory_location_id']);
            }
        }

        $page->call('save');
    }

    public function test_a_name_alone_is_enough_to_stage_an_item(): void
    {
        $this->stage([[
            'name' => '2026 Topps Chrome Hobby',
        ]]);

        $line = $this->pallet->lines()->firstOrFail();

        $this->assertSame('2026 Topps Chrome Hobby', $line->description);
        $this->assertNull($line->inventory_item_id, 'It should stage without inventing a product.');
        $this->assertSame(1, (int) $line->case_count);

        // The location is allowed to be filled in — this suite has a single
        // location, which is the case where the question has no content. What
        // must stay unanswered is the product, because that is the fact the
        // barcode settles later.
        $this->assertFalse($line->isFullyMapped(), 'It is not receivable until an item is linked.');
    }

    public function test_case_or_single_is_recorded_when_known_and_left_open_when_not(): void
    {
        // Worth capturing at staging because it is the moment somebody is
        // actually looking at the thing, and it decides what the product
        // becomes later.
        $this->stage([['name' => 'A case', 'form_factor' => 'container']]);
        $this->stage([['name' => 'A single', 'form_factor' => 'single']]);
        $this->stage([['name' => 'No idea', 'form_factor' => 'unknown']]);

        $lines = $this->pallet->lines()->orderBy('line_number')->get();

        $this->assertTrue($lines[0]->is_container);
        $this->assertFalse($lines[1]->is_container);
        $this->assertNull($lines[2]->is_container, 'Unknown is a real answer, not "not a case".');
    }

    public function test_an_existing_item_can_still_be_linked_up_front(): void
    {
        $item = InventoryItem::create(['name' => 'Known Box', 'sku' => 'KB-1', 'is_active' => true]);

        $this->stage([[
            'name'                  => 'Known Box',
            'inventory_item_id'     => $item->id,
            'inventory_location_id' => $this->location->id,
            'case_count'            => 3,
        ]]);

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

    public function test_a_row_can_be_scanned_and_counted_in_one_go(): void
    {
        // The flow as it is actually worked: the line is clicked, so nothing
        // has to be looked up, and the box being scanned is the box in hand —
        // so the same action links it and counts it.
        $this->stage([['name' => 'Row Box', 'case_count' => 2]]);
        $line = $this->pallet->lines()->firstOrFail();

        $this->page()->callAction(
            'linkLine',
            ['code' => '6060606060', 'inventory_location_id' => $this->location->id, 'confirm_case' => true],
            arguments: ['line' => $line->id],
        );

        $line->refresh();

        $this->assertNotNull($line->inventory_item_id, 'Scanning should have linked it.');
        $this->assertSame(1, $line->receivedCases(), 'and counted the one being held.');
    }

    public function test_a_camera_scan_links_and_counts_with_no_form_at_all(): void
    {
        // The whole interaction: press the row's button, point the camera,
        // done. Every question the form asked already has an answer by then —
        // the line from the button, the code from the scan, the location from
        // the pallet.
        \App\Models\Setting::set('default_receiving_location_id', (string) $this->location->id);

        $this->stage([['name' => 'Camera Box', 'case_count' => 2]]);
        $line = $this->pallet->lines()->firstOrFail();

        $this->page()->call('scanLineIntoInventory', $line->id, '8080808080');

        $line->refresh();

        $this->assertNotNull($line->inventory_item_id);
        $this->assertSame('8080808080', $line->inventoryItem->barcode);
        $this->assertSame(1, $line->receivedCases(), 'The box in front of the camera is a case received.');
    }

    public function test_a_camera_scan_with_nowhere_to_put_it_falls_back_to_the_form(): void
    {
        // The location is the one thing a scan cannot supply. Rather than fail
        // on the last step of a scan that worked, it hands over to the form
        // with the code already filled in.
        \App\Models\Setting::set('default_receiving_location_id', '');
        InventoryLocation::query()->update(['status' => 'inactive']);
        cache()->forget('inv_loc:active');

        $line = PalletLine::create([
            'pallet_id'   => $this->pallet->id,
            'line_number' => 1,
            'description' => 'Nowhere To Go',
            'case_count'  => 1,
        ]);

        $this->page()
            ->call('scanLineIntoInventory', $line->id, '9090909090')
            ->assertActionMounted('linkLine');

        $this->assertNull($line->fresh()->inventory_item_id, 'Nothing should have been linked yet.');
    }

    public function test_a_camera_scan_cannot_reach_another_pallets_line(): void
    {
        $other = Pallet::create([
            'vendor_id' => $this->pallet->vendor_id,
            'reference' => 'PO-ELSEWHERE',
            'status'    => 'staged',
        ]);

        $foreign = PalletLine::create([
            'pallet_id'   => $other->id,
            'line_number' => 1,
            'description' => 'Not yours',
            'case_count'  => 1,
        ]);

        $this->page()->call('scanLineIntoInventory', $foreign->id, '1010101010');

        $this->assertNull($foreign->fresh()->inventory_item_id);
    }

    public function test_linking_without_counting_leaves_the_case_open(): void
    {
        // Prepping ahead of the delivery: link it now, count it when it lands.
        $this->stage([['name' => 'Later Box']]);
        $line = $this->pallet->lines()->firstOrFail();

        $this->page()->callAction(
            'linkLine',
            ['code' => '7070707070', 'inventory_location_id' => $this->location->id, 'confirm_case' => false],
            arguments: ['line' => $line->id],
        );

        $line->refresh();

        $this->assertNotNull($line->inventory_item_id);
        $this->assertSame(0, $line->receivedCases());
    }

    public function test_a_mapped_row_counts_a_case_per_click(): void
    {
        $item = InventoryItem::create(['name' => 'Clicky', 'sku' => 'CL-1', 'is_active' => true]);

        $line = PalletLine::create([
            'pallet_id'             => $this->pallet->id,
            'line_number'           => 1,
            'description'           => 'Clicky',
            'inventory_item_id'     => $item->id,
            'inventory_location_id' => $this->location->id,
            'case_count'            => 2,
        ]);

        $this->page()->callAction('confirmCase', arguments: ['line' => $line->id]);
        $this->assertSame(1, $line->fresh()->receivedCases());

        $this->page()->callAction('confirmCase', arguments: ['line' => $line->id]);
        $this->assertSame(2, $line->fresh()->receivedCases());
        $this->assertSame('received', $line->fresh()->line_status);
    }

    public function test_a_row_action_cannot_reach_another_pallets_line(): void
    {
        // Resolved through this pallet's own lines, so a stale page cannot
        // credit stock against a pallet it is not looking at.
        $other = Pallet::create([
            'vendor_id' => $this->pallet->vendor_id,
            'reference' => 'PO-OTHER',
            'status'    => 'staged',
        ]);

        $foreign = PalletLine::create([
            'pallet_id'             => $other->id,
            'line_number'           => 1,
            'description'           => 'Not yours',
            'inventory_item_id'     => InventoryItem::create(['name' => 'X', 'sku' => 'X-1', 'is_active' => true])->id,
            'inventory_location_id' => $this->location->id,
            'case_count'            => 1,
        ]);

        $this->page()->callAction('confirmCase', arguments: ['line' => $foreign->id]);

        $this->assertSame(0, $foreign->fresh()->receivedCases());
    }

    public function test_the_configured_receiving_location_is_used_without_asking(): void
    {
        // Set once in Settings and the question stops being asked. A pallet
        // goes into one place and is sorted afterwards, so answering per line
        // while holding a box is the same answer typed over and over.
        \App\Models\Setting::set('default_receiving_location_id', (string) $this->location->id);

        $this->assertSame($this->location->id, \App\Models\InventoryLocation::defaultReceivingId());

        $this->stage([['name' => 'Defaulted']]);

        $this->assertSame(
            $this->location->id,
            $this->pallet->lines()->firstOrFail()->inventory_location_id,
            'A staged line should already know where it is going.'
        );
    }

    public function test_an_inactive_configured_location_is_ignored(): void
    {
        // Pointing at somewhere retired would map lines into a location that
        // no longer accepts stock, which is worse than asking.
        $retired = InventoryLocation::create(['name' => 'Old Bay', 'type' => 'main_storage', 'status' => 'inactive']);
        \App\Models\Setting::set('default_receiving_location_id', (string) $retired->id);

        $this->assertNotSame($retired->id, \App\Models\InventoryLocation::defaultReceivingId());
    }

    public function test_with_one_location_it_does_not_ask_at_all(): void
    {
        // Nothing configured, and only one place stock can go — the question
        // has no content.
        \App\Models\Setting::set('default_receiving_location_id', '');
        InventoryLocation::where('id', '!=', $this->location->id)->delete();
        cache()->forget('inv_loc:active');

        $this->assertSame($this->location->id, \App\Models\InventoryLocation::defaultReceivingId());
    }

    public function test_creating_a_pallet_leads_straight_into_its_manifest(): void
    {
        // A pallet exists to hold lines, so the next thing anyone does after
        // creating one is type them. The create form no longer carries a line
        // editor of its own — two editors for one thing is how they drift.
        Livewire::test(\App\Filament\Resources\PalletResource\Pages\CreatePallet::class)
            ->fillForm([
                'vendor_id' => $this->pallet->vendor_id,
                'reference' => 'PO-FROM-FORM',
                'status'    => 'staged',
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect(\App\Filament\Resources\PalletResource::getUrl('add-lines', [
                'record' => Pallet::firstWhere('reference', 'PO-FROM-FORM'),
            ]));
    }

    public function test_a_line_can_be_staged_from_a_name_alone(): void
    {
        // Cases and units per box are unknowable from some slips and editable
        // afterwards, so a name has to be enough on its own.
        Livewire::test(\App\Filament\Resources\PalletResource\Pages\AddPalletLines::class, ['record' => $this->pallet->id])
            ->set('rows.0.description', 'Just a name')
            ->set('rows.1.description', 'A case of something')
            ->set('rows.1.is_container', '1')
            ->call('save');

        $lines = $this->pallet->lines()->orderBy('line_number')->get();

        $this->assertSame('Just a name', $lines[0]->description);
        $this->assertNull($lines[0]->inventory_item_id);
        $this->assertTrue((bool) $lines[1]->is_container);
    }

    public function test_a_line_can_point_at_an_existing_item(): void
    {
        // "More stock of something I already have" — the product exists and
        // this pallet is topping it up.
        $item = InventoryItem::create(['name' => 'Restock Me', 'sku' => 'RM-1', 'is_active' => true]);

        Livewire::test(\App\Filament\Resources\PalletResource\Pages\AddPalletLines::class, ['record' => $this->pallet->id])
            ->set('rows.0.description', 'Restock Me')
            ->set('rows.0.inventory_item_id', (string) $item->id)
            ->call('save');

        $this->assertSame($item->id, PalletLine::firstWhere('description', 'Restock Me')->inventory_item_id);
    }

}
