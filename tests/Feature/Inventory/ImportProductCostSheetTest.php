<?php

namespace Tests\Feature\Inventory;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * The importer is tested against real .xlsx files rather than an array of
 * rows: the parts that break are the ones between the file and the array —
 * a header in an unexpected column, a formula string where a number was
 * expected, a sheet that runs on for hundreds of blank rows.
 */
class ImportProductCostSheetTest extends TestCase
{
    use RefreshDatabase;

    private string $path;

    protected function setUp(): void
    {
        parent::setUp();
        $this->path = storage_path('framework/testing/cost-sheet-' . uniqid() . '.xlsx');
    }

    protected function tearDown(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
        }

        parent::tearDown();
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     */
    private function workbook(array $rows, ?array $header = null, string $title = 'Product cost ref sheet'): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle($title);

        $sheet->fromArray(
            array_merge(
                [$header ?? ['PRODUCT NAME', 'SKU', 'Type', 'Auction or BIN?', 'Cost', 'Sale price / Target', 'Margin potential']],
                $rows,
            ),
            null,
            'A1',
        );

        (new Xlsx($spreadsheet))->save($this->path);

        return $this->path;
    }

    public function test_it_creates_products_with_costs_targets_and_types(): void
    {
        $this->workbook([
            ['Gem 1 Box', 'B102025', 'box', 'Both', 50, 92, '=F2-E2'],
            ['Loose Packs', null, 'pack', 'Auction', 3.6, 10, '=F3-E3'],
        ]);

        $this->artisan('inventory:import-cost-sheet', ['file' => $this->path])
            ->assertSuccessful();

        $gem = Product::where('name', 'Gem 1 Box')->firstOrFail();

        $this->assertSame('B102025', $gem->sku);
        $this->assertSame('box', $gem->product_type);
        $this->assertSame('50.00', $gem->unit_cost);
        $this->assertSame('92.00', $gem->sale_price);
        $this->assertSame(42.0, $gem->marginPotential());
        $this->assertStringContainsString('Sold as: Both', $gem->notes);
        $this->assertTrue($gem->is_active);

        // No SKU in the sheet, so the model's own generator supplies one.
        $this->assertNotEmpty(Product::where('name', 'Loose Packs')->firstOrFail()->sku);
    }

    public function test_running_it_twice_updates_rather_than_duplicates(): void
    {
        $this->workbook([['Gem 1 Box', 'B102025', 'box', 'Both', 50, 92, null]]);

        $this->artisan('inventory:import-cost-sheet', ['file' => $this->path])->assertSuccessful();
        $this->artisan('inventory:import-cost-sheet', ['file' => $this->path])->assertSuccessful();

        $this->assertSame(1, Product::where('name', 'Gem 1 Box')->count());
    }

    public function test_it_matches_an_existing_product_by_name_ignoring_case_and_padding(): void
    {
        $existing = Product::create(['name' => 'gem 1 box  ', 'is_active' => true]);

        $this->workbook([['Gem 1 Box', null, 'box', 'Both', 50, 92, null]]);

        $this->artisan('inventory:import-cost-sheet', ['file' => $this->path])->assertSuccessful();

        $this->assertSame(1, Product::count());
        $this->assertSame('92.00', $existing->fresh()->sale_price);
    }

    public function test_it_does_not_overwrite_prices_someone_has_already_corrected(): void
    {
        // A cost sheet is a starting point, not the authority. A re-import
        // must not stamp over a number the warehouse has since fixed.
        $existing = Product::create(['name' => 'Gem 1 Box', 'unit_cost' => 61, 'sale_price' => 120, 'is_active' => true]);

        $this->workbook([['Gem 1 Box', null, 'box', 'Both', 50, 92, null]]);

        $this->artisan('inventory:import-cost-sheet', ['file' => $this->path])->assertSuccessful();

        $existing->refresh();
        $this->assertSame('61.00', $existing->unit_cost);
        $this->assertSame('120.00', $existing->sale_price);
    }

    public function test_overwrite_prices_replaces_them_when_asked(): void
    {
        $existing = Product::create(['name' => 'Gem 1 Box', 'unit_cost' => 61, 'sale_price' => 120, 'is_active' => true]);

        $this->workbook([['Gem 1 Box', null, 'box', 'Both', 50, 92, null]]);

        $this->artisan('inventory:import-cost-sheet', [
            'file'                => $this->path,
            '--overwrite-prices'  => true,
        ])->assertSuccessful();

        $existing->refresh();
        $this->assertSame('50.00', $existing->unit_cost);
        $this->assertSame('92.00', $existing->sale_price);
    }

    public function test_a_dry_run_writes_nothing(): void
    {
        $this->workbook([['Gem 1 Box', null, 'box', 'Both', 50, 92, null]]);

        $this->artisan('inventory:import-cost-sheet', ['file' => $this->path, '--dry-run' => true])
            ->assertSuccessful();

        $this->assertSame(0, Product::count());
    }

    public function test_it_finds_its_columns_after_one_is_inserted(): void
    {
        // Reading by position breaks silently the first time somebody adds a
        // column, and the damage looks like bad data rather than bad layout:
        // costs landing in a name, or every price reading null.
        $this->workbook(
            [['Gem 1 Box', 'Vendor A', 'B102025', 'box', 'Both', 50, 92]],
            ['PRODUCT NAME', 'Supplier', 'SKU', 'Type', 'Auction or BIN?', 'Cost', 'Sale price / Target'],
        );

        $this->artisan('inventory:import-cost-sheet', ['file' => $this->path])->assertSuccessful();

        $product = Product::where('name', 'Gem 1 Box')->firstOrFail();
        $this->assertSame('B102025', $product->sku);
        $this->assertSame('50.00', $product->unit_cost);
        $this->assertSame('92.00', $product->sale_price);
    }

    public function test_a_formula_in_a_price_cell_is_ignored_rather_than_read_as_zero(): void
    {
        // The sheet's own margin column is full of "=F2-E2", so a price cell
        // holding one is a matter of a row being copied slightly wrong. Cast
        // straight to float it becomes 0.0 — a silent, plausible, wrong price.
        $this->workbook([['Gem 1 Box', null, 'box', 'Both', 50, '=E2*2', null]]);

        $this->artisan('inventory:import-cost-sheet', ['file' => $this->path])->assertSuccessful();

        $product = Product::where('name', 'Gem 1 Box')->firstOrFail();

        // Left unset, so it reads as "no target" rather than "$0.00 target".
        $this->assertNull($product->sale_price);
        $this->assertNull($product->marginPotential());
        // The rest of the row still imported.
        $this->assertSame('50.00', $product->unit_cost);
    }

    public function test_blank_rows_below_the_data_are_not_imported(): void
    {
        $this->workbook([
            ['Gem 1 Box', null, 'box', 'Both', 50, 92, null],
            [null, null, null, null, null, null, null],
            [null, null, null, null, null, null, null],
        ]);

        $this->artisan('inventory:import-cost-sheet', ['file' => $this->path])->assertSuccessful();

        $this->assertSame(1, Product::count());
    }

    public function test_it_fails_clearly_on_a_file_that_is_not_there(): void
    {
        $this->artisan('inventory:import-cost-sheet', ['file' => '/tmp/nope.xlsx'])
            ->expectsOutputToContain('No file at')
            ->assertFailed();
    }

    public function test_it_says_so_when_the_worksheet_has_no_product_name_column(): void
    {
        $this->workbook([['something', 'else']], ['Column A', 'Column B']);

        $this->artisan('inventory:import-cost-sheet', ['file' => $this->path])
            ->expectsOutputToContain('PRODUCT NAME')
            ->assertFailed();
    }
}
