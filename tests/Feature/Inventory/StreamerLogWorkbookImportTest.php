<?php

namespace Tests\Feature\Inventory;

use App\Services\ProductSheetImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * The whole "Streamer Log" workbook downloaded from Google Sheets: the
 * Calculations tab comes first, and the product list leaves its name
 * column's header blank.
 */
class StreamerLogWorkbookImportTest extends TestCase
{
    use RefreshDatabase;

    private string $path;

    protected function setUp(): void
    {
        parent::setUp();
        $this->path = storage_path('framework/testing/streamer-log-'.uniqid().'.xlsx');
        $book = new Spreadsheet();
        $calc = $book->getActiveSheet()->setTitle('Calculations (Print)');
        $calc->fromArray([
            ['Select product sold from the menu under "Product" and type in the number sold.'],
            [],
            ['Product', 'Product Cost', 'Number Sold', 'Total Cost'],
            ['Gem 4 Box', 22, null, 0],
        ]);
        $book->createSheet()->setTitle('Hardcopy (Print)')->fromArray([['Streamer', 'Date']]);
        $book->createSheet()->setTitle('Product cost ref sheet')->fromArray([
            [null, 'SKU', 'Type', 'Auction or BIN?', 'Cost', 'Sale price / Target'],
            ['Sharp Blade Coin Box (Per bag)', null, 'pack', 'Auction', 5.5, 15],
            ['Gem 2 Booster box', null, 'box', 'Both', 65, 106],
            ['Brillant fantasy Booster box Jumbo', null, 'box', 'Both', 41, null],
            [null, null, null, null, null, null],
        ]);
        $book->createSheet()->setTitle('Sheet11');
        (new Xlsx($book))->save($this->path);
    }

    protected function tearDown(): void
    {
        @unlink($this->path);
        parent::tearDown();
    }

    public function test_it_picks_the_product_sheet_and_reads_the_unlabelled_name_column(): void
    {
        $importer = app(ProductSheetImporter::class);
        $sheets = $importer->sheetNames($this->path);

        $this->assertSame('Product cost ref sheet', $importer->guessSheet($this->path, $sheets));

        $rows = $importer->read($this->path);
        $this->assertCount(3, $rows);
        $this->assertSame('Sharp Blade Coin Box (Per bag)', $rows[0]['name']);
        $this->assertSame(2, $rows[0]['line']);
        $this->assertSame(5.5, $rows[0]['cost']);
        $this->assertSame(15.0, $rows[0]['sale_price']);
        $this->assertSame('Both', $rows[1]['sold_as']);
        $this->assertNull($rows[2]['sale_price']);
    }

    public function test_the_command_imports_the_whole_workbook(): void
    {
        $this->artisan('inventory:import-cost-sheet', ['file' => $this->path])->assertSuccessful();

        $this->assertDatabaseHas('products', ['name' => 'Gem 2 Booster box']);
        $this->assertDatabaseCount('products', 3);
    }

    public function test_the_calculations_tab_says_what_to_download_instead(): void
    {
        $this->expectExceptionMessage('This is the Calculations tab');
        app(ProductSheetImporter::class)->read($this->path, 'Calculations (Print)');
    }
}
