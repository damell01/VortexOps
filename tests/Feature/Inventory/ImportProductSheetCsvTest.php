<?php

namespace Tests\Feature\Inventory;

use App\Models\Product;
use App\Services\ProductSheetImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Importing a CSV, which is what people actually export.
 *
 * The screen offers "an .xlsx, .xls or .csv" and then asked every file for its
 * list of worksheets. Only Xlsx, Xls, Ods, Xml and Gnumeric implement that;
 * PhpSpreadsheet's CSV reader does not, so the call was a fatal —
 * "Call to undefined method ...Reader\Csv::listWorksheetNames()" — surfaced as
 * "That file could not be opened", which blames a perfectly good file.
 *
 * A CSV holds exactly one sheet, so there is nothing to choose and nothing to
 * validate. Naming a sheet it does not have would load no rows at all.
 */
class ImportProductSheetCsvTest extends TestCase
{
    use RefreshDatabase;

    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = tempnam(sys_get_temp_dir(), 'sheet') . '.csv';

        file_put_contents($this->path, <<<'CSV'
        PRODUCT NAME,SKU,Type,Auction or BIN?,Cost,Sale price / Target
        2025 Topps Chrome Hobby Box,TC25-HB,Sealed,Auction,89.50,140.00
        2025 Prizm Blaster,PZ25-BL,Sealed,buy it now,24.99,39.99
        CSV);
    }

    protected function tearDown(): void
    {
        @unlink($this->path);

        parent::tearDown();
    }

    private function importer(): ProductSheetImporter
    {
        return app(ProductSheetImporter::class);
    }

    public function test_a_csv_offers_one_sheet_instead_of_throwing(): void
    {
        $this->assertSame(
            [ProductSheetImporter::SINGLE_SHEET],
            $this->importer()->sheetNames($this->path),
        );
    }

    public function test_a_csv_reads_its_rows(): void
    {
        $rows = $this->importer()->read($this->path);

        $this->assertCount(2, $rows);
        $this->assertSame('2025 Topps Chrome Hobby Box', $rows[0]['name']);
        $this->assertSame('TC25-HB', $rows[0]['sku']);
        $this->assertSame(89.50, $rows[0]['cost']);
        $this->assertSame(140.00, $rows[0]['sale_price']);
    }

    public function test_the_sold_as_column_is_still_normalised_from_a_csv(): void
    {
        // read() keeps the sheet's own words; normalising is the write's job.
        $importer = $this->importer();
        $importer->apply($importer->read($this->path));

        $this->assertSame('Auction', Product::where('sku', 'TC25-HB')->value('sold_as'));
        $this->assertSame('BIN', Product::where('sku', 'PZ25-BL')->value('sold_as'));
    }

    public function test_a_csv_plans_and_applies_like_a_workbook(): void
    {
        $importer = $this->importer();
        $rows     = $importer->read($this->path);
        $plan     = $importer->plan($rows);

        $this->assertCount(2, $plan['rows']);
        $this->assertSame(2, $plan['summary']['create']);

        $importer->apply($rows);

        $this->assertSame(2, Product::count());
        $this->assertSame('TC25-HB', Product::where('name', '2025 Topps Chrome Hobby Box')->value('sku'));
    }

    public function test_naming_a_sheet_a_csv_does_not_have_is_ignored_rather_than_fatal(): void
    {
        // The console command defaults to the workbook's sheet name. Applied to
        // a CSV that would have matched nothing and loaded no rows.
        $rows = $this->importer()->read($this->path, ProductSheetImporter::DEFAULT_SHEET);

        $this->assertCount(2, $rows);
    }
}
