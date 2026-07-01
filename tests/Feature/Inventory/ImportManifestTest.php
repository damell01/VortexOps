<?php

namespace Tests\Feature\Inventory;

use App\Filament\Resources\PalletResource\Pages\ImportManifest;
use App\Models\AiTask;
use App\Models\InventoryItem;
use App\Models\Pallet;
use App\Models\PalletLine;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportManifestTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Pallet $pallet;
    private AiTask $task;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $vendor = Vendor::create(['name' => 'Test Vendor', 'status' => 'active']);

        $this->pallet = Pallet::create([
            'vendor_id'  => $vendor->id,
            'reference'  => 'PO-100',
            'status'     => 'pending',
            'created_by' => $this->user->id,
        ]);

        $this->task = AiTask::create([
            'type'          => 'parse_pallet_slip',
            'status'        => 'pending',
            'taskable_type' => Pallet::class,
            'taskable_id'   => $this->pallet->id,
            'triggered_by'  => $this->user->id,
            'input'         => [],
        ]);
    }

    private function makePage(): ImportManifest
    {
        $page           = new ImportManifest();
        $page->record   = $this->pallet->load('vendor');
        $page->aiTaskId = $this->task->id;
        $page->stage    = 'processing';
        return $page;
    }

    private function blankLine(string $description = '', int $caseCount = 1): array
    {
        return ['description' => $description, 'case_count' => (string) $caseCount, 'unit_cost' => '', 'sku' => '', 'barcode' => ''];
    }

    // ── checkProcessing ───────────────────────────────────────────────────────

    public function test_check_processing_transitions_to_verify_on_completed_task(): void
    {
        $this->task->markCompleted(['lines' => [
            ['description' => 'Hobby Box 2024', 'case_count' => 3,    'unit_cost' => 89.99, 'sku' => 'HB2024', 'barcode' => null],
            ['description' => 'Blaster Box',    'case_count' => 5,    'unit_cost' => null,  'sku' => null,     'barcode' => null],
        ]]);

        $page = $this->makePage();
        $page->checkProcessing();

        $this->assertEquals('verify', $page->stage);
        $this->assertCount(2, $page->parsedLines);

        $line = $page->parsedLines[0];
        $this->assertEquals('Hobby Box 2024', $line['description']);
        $this->assertEquals('3', $line['case_count']);
        $this->assertEquals('89.99', $line['unit_cost']);
        $this->assertEquals('HB2024', $line['sku']);

        // null unit_cost becomes empty string
        $this->assertEquals('', $page->parsedLines[1]['unit_cost']);
    }

    public function test_check_processing_returns_to_upload_on_failed_task(): void
    {
        $this->task->markFailed('Vision model not available');

        $page = $this->makePage();
        $page->checkProcessing();

        $this->assertEquals('upload', $page->stage);
        $this->assertEquals('Vision model not available', $page->parseError);
    }

    public function test_check_processing_does_nothing_while_still_pending(): void
    {
        $page = $this->makePage();
        $page->checkProcessing();

        $this->assertEquals('processing', $page->stage);
        $this->assertEmpty($page->parsedLines);
    }

    public function test_check_processing_does_nothing_without_task_id(): void
    {
        $page           = $this->makePage();
        $page->aiTaskId = null;
        $page->checkProcessing();

        $this->assertEquals('processing', $page->stage);
    }

    // ── addLine / removeLine ──────────────────────────────────────────────────

    public function test_add_line_appends_blank_row(): void
    {
        $page              = $this->makePage();
        $page->parsedLines = [$this->blankLine('Existing Line')];
        $page->addLine();

        $this->assertCount(2, $page->parsedLines);
        $this->assertEquals('', $page->parsedLines[1]['description']);
        $this->assertEquals('1', $page->parsedLines[1]['case_count']);
    }

    public function test_remove_line_deletes_correct_index_and_reindexes(): void
    {
        $page              = $this->makePage();
        $page->parsedLines = [
            $this->blankLine('First'),
            $this->blankLine('Second'),
            $this->blankLine('Third'),
        ];
        $page->removeLine(1);

        $this->assertCount(2, $page->parsedLines);
        $this->assertEquals('First', $page->parsedLines[0]['description']);
        $this->assertEquals('Third', $page->parsedLines[1]['description']);
    }

    public function test_remove_line_handles_first_element(): void
    {
        $page              = $this->makePage();
        $page->parsedLines = [$this->blankLine('Only'), $this->blankLine('Second')];
        $page->removeLine(0);

        $this->assertCount(1, $page->parsedLines);
        $this->assertEquals('Second', $page->parsedLines[0]['description']);
    }

    // ── import ────────────────────────────────────────────────────────────────

    public function test_import_creates_pallet_lines_and_transitions_to_done(): void
    {
        $page              = $this->makePage();
        $page->stage       = 'verify';
        $page->parsedLines = [
            ['description' => 'Hobby Box 2024', 'case_count' => '3', 'unit_cost' => '89.99', 'sku' => '', 'barcode' => ''],
            ['description' => 'Blaster Box',    'case_count' => '5', 'unit_cost' => '',       'sku' => '', 'barcode' => ''],
        ];

        $page->import();

        $this->assertEquals('done', $page->stage);
        $this->assertEquals(2, $page->created);
        $this->assertEquals(0, $page->matched);
        $this->assertEquals(2, $page->unmatched);

        $this->assertDatabaseHas('pallet_lines', [
            'pallet_id'   => $this->pallet->id,
            'description' => 'Hobby Box 2024',
            'case_count'  => 3,
        ]);
        $this->assertDatabaseHas('pallet_lines', [
            'pallet_id'   => $this->pallet->id,
            'description' => 'Blaster Box',
            'case_count'  => 5,
        ]);
    }

    public function test_import_matches_item_by_barcode(): void
    {
        $item = InventoryItem::create([
            'name'         => 'Chrome Hobby',
            'barcode'      => '012345678901',
            'unit_cost'    => 0,
            'average_cost' => 0,
            'is_active'    => true,
        ]);

        $page              = $this->makePage();
        $page->parsedLines = [
            ['description' => 'Chrome Hobby Box', 'case_count' => '2', 'unit_cost' => '', 'sku' => '', 'barcode' => '012345678901'],
        ];
        $page->import();

        $line = PalletLine::where('pallet_id', $this->pallet->id)->first();
        $this->assertEquals($item->id, $line->inventory_item_id);
        $this->assertEquals(1, $page->matched);
    }

    public function test_import_matches_item_by_sku_when_no_barcode(): void
    {
        $item = InventoryItem::create([
            'name'         => 'Prizm Hobby',
            'sku'          => 'PRZ-2024',
            'unit_cost'    => 0,
            'average_cost' => 0,
            'is_active'    => true,
        ]);

        $page              = $this->makePage();
        $page->parsedLines = [
            ['description' => 'Prizm Hobby Box', 'case_count' => '1', 'unit_cost' => '', 'sku' => 'PRZ-2024', 'barcode' => ''],
        ];
        $page->import();

        $line = PalletLine::where('pallet_id', $this->pallet->id)->first();
        $this->assertEquals($item->id, $line->inventory_item_id);
    }

    public function test_import_matches_item_by_exact_name_as_last_resort(): void
    {
        $item = InventoryItem::create([
            'name'         => 'Select Hobby Box',
            'unit_cost'    => 0,
            'average_cost' => 0,
            'is_active'    => true,
        ]);

        $page              = $this->makePage();
        $page->parsedLines = [
            ['description' => 'Select Hobby Box', 'case_count' => '1', 'unit_cost' => '', 'sku' => '', 'barcode' => ''],
        ];
        $page->import();

        $this->assertEquals($item->id, PalletLine::where('pallet_id', $this->pallet->id)->value('inventory_item_id'));
    }

    public function test_import_skips_rows_with_blank_description(): void
    {
        $page              = $this->makePage();
        $page->parsedLines = [
            $this->blankLine(''),            // should be skipped
            $this->blankLine('Real Product'),
        ];
        $page->import();

        $this->assertEquals(1, $page->created);
        $this->assertEquals(1, PalletLine::where('pallet_id', $this->pallet->id)->count());
    }

    public function test_import_falls_back_to_item_unit_cost_when_slip_cost_is_blank(): void
    {
        $item = InventoryItem::create([
            'name'         => 'Bowman Chrome',
            'unit_cost'    => 45.00,
            'average_cost' => 0,
            'is_active'    => true,
        ]);

        $page              = $this->makePage();
        $page->parsedLines = [
            ['description' => 'Bowman Chrome', 'case_count' => '1', 'unit_cost' => '', 'sku' => '', 'barcode' => ''],
        ];
        $page->import();

        $line = PalletLine::where('pallet_id', $this->pallet->id)->first();
        $this->assertEquals(45.00, (float) $line->unit_cost);
    }

    public function test_import_uses_slip_cost_over_item_cost(): void
    {
        InventoryItem::create([
            'name'         => 'Gold Label',
            'unit_cost'    => 200.00,
            'average_cost' => 0,
            'is_active'    => true,
        ]);

        $page              = $this->makePage();
        $page->parsedLines = [
            ['description' => 'Gold Label', 'case_count' => '1', 'unit_cost' => '175.00', 'sku' => '', 'barcode' => ''],
        ];
        $page->import();

        $line = PalletLine::where('pallet_id', $this->pallet->id)->first();
        $this->assertEquals(175.00, (float) $line->unit_cost);
    }

    public function test_import_line_numbers_continue_from_existing_max(): void
    {
        PalletLine::create([
            'pallet_id'   => $this->pallet->id,
            'line_number' => 5,
            'description' => 'Pre-existing line',
            'case_count'  => 1,
            'unit_cost'   => 0,
        ]);

        $page              = $this->makePage();
        $page->parsedLines = [
            $this->blankLine('New A'),
            $this->blankLine('New B'),
        ];
        $page->import();

        $numbers = PalletLine::where('pallet_id', $this->pallet->id)
            ->orderBy('line_number')
            ->pluck('line_number')
            ->toArray();

        $this->assertEquals([5, 6, 7], $numbers);
    }

    public function test_import_handles_dollar_sign_in_unit_cost(): void
    {
        $page              = $this->makePage();
        $page->parsedLines = [
            ['description' => 'Fancy Box', 'case_count' => '1', 'unit_cost' => '$99.50', 'sku' => '', 'barcode' => ''],
        ];
        $page->import();

        $line = PalletLine::where('pallet_id', $this->pallet->id)->first();
        $this->assertEquals(99.50, (float) $line->unit_cost);
    }
}
