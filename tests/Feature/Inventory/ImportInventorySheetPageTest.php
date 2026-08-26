<?php

namespace Tests\Feature\Inventory;

use App\Filament\Pages\ImportInventorySheet;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * The import screen.
 *
 * What matters here is the promise the screen makes: that the table you are
 * shown is what the button will do. So the tests upload a real workbook, read
 * the plan, and then check the catalogue matches the plan afterwards — not
 * that the component has the right properties set.
 */
class ImportInventorySheetPageTest extends TestCase
{
    use RefreshDatabase;

    private string $path;

    protected function setUp(): void
    {
        parent::setUp();
        $this->path = storage_path('framework/testing/import-page-' . uniqid() . '.xlsx');
    }

    protected function tearDown(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
        }

        parent::tearDown();
    }

    /** @param array<int, array<int, mixed>> $rows */
    private function workbook(array $rows, string $title = 'Product cost ref sheet'): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle($title);
        $sheet->fromArray(
            array_merge([['PRODUCT NAME', 'SKU', 'Type', 'Auction or BIN?', 'Cost', 'Sale price / Target']], $rows),
            null,
            'A1',
        );

        (new Xlsx($spreadsheet))->save($this->path);

        return UploadedFile::fake()->createWithContent('cost-sheet.xlsx', file_get_contents($this->path));
    }

    private function admin(): User
    {
        return (User::firstWhere('email', config('app.owner_email'))
            ?? User::factory()->create(['email' => config('app.owner_email')]))->fresh();
    }

    private function page()
    {
        return Livewire::actingAs($this->admin())->test(ImportInventorySheet::class);
    }

    public function test_a_csv_upload_previews_the_same_way_a_workbook_does(): void
    {
        // The screen has always offered ".xlsx, .xls or .csv" and the upload
        // rules have always accepted csv — but every test used a workbook, so
        // nothing noticed that a CSV died on the worksheet list with
        // "Call to undefined method ...Reader\Csv::listWorksheetNames()",
        // shown as "That file could not be opened".
        $csv = UploadedFile::fake()->createWithContent('cost-sheet.csv', <<<'CSV'
        PRODUCT NAME,SKU,Type,Auction or BIN?,Cost,Sale price / Target
        2026 Topps Chrome Hobby Box,TCH-2026,box,BIN,189.99,249.99
        2026 Prizm Football Blaster,PZF-2026,box,Auction,24.50,39.99
        CSV);

        $page = $this->page()->set('upload', $csv);

        $this->assertNull($page->get('error'), 'the CSV was rejected: ' . (string) $page->get('error'));
        $this->assertSame(2, $page->get('summary')['create']);
        $this->assertSame(0, Product::count());
    }

    public function test_importing_a_csv_writes_what_the_preview_promised(): void
    {
        $csv = UploadedFile::fake()->createWithContent('cost-sheet.csv', <<<'CSV'
        PRODUCT NAME,SKU,Type,Auction or BIN?,Cost,Sale price / Target
        2026 Topps Chrome Hobby Box,TCH-2026,box,BIN,189.99,249.99
        CSV);

        $this->page()->set('upload', $csv)->call('import');

        $product = Product::firstWhere('sku', 'TCH-2026');

        $this->assertNotNull($product);
        $this->assertSame('BIN', $product->sold_as);
        $this->assertEquals(189.99, (float) $product->unit_cost);
    }

    public function test_it_previews_without_writing_anything(): void
    {
        $file = $this->workbook([
            ['2026 Topps Chrome Hobby Box', 'TCH-2026', 'box', 'BIN', 189.99, 249.99],
            ['2026 Prizm Football Blaster', 'PZF-2026', 'box', 'Auction', 24.50, 39.99],
        ]);

        $page = $this->page()->set('upload', $file);

        $this->assertSame(2, $page->get('summary')['create']);
        $this->assertSame(0, $page->get('summary')['update']);

        // The whole point of the screen: looking is not importing.
        $this->assertSame(0, Product::count());
    }

    public function test_the_preview_says_what_will_change_on_an_existing_item(): void
    {
        Product::create(['name' => '2026 Topps Chrome Hobby Box', 'sku' => 'TCH-2026', 'unit_cost' => 0, 'sale_price' => null, 'is_active' => true]);

        $file = $this->workbook([['2026 Topps Chrome Hobby Box', 'TCH-2026', 'box', 'BIN', 189.99, 249.99]]);

        $page = $this->page()->set('upload', $file);
        $rows = $page->get('rows');

        $this->assertSame('update', $rows[0]['action']);
        $this->assertSame('sku', $rows[0]['matched_by']);

        $fields = array_column($rows[0]['changes'], 'field');
        $this->assertContains('Sale target', $fields);
    }

    public function test_importing_writes_exactly_what_the_preview_promised(): void
    {
        $file = $this->workbook([
            ['2026 Topps Chrome Hobby Box', 'TCH-2026', 'box', 'BIN', 189.99, 249.99],
            ['2026 Prizm Football Blaster', 'PZF-2026', 'box', 'Auction', 24.50, 39.99],
        ]);

        $page    = $this->page()->set('upload', $file);
        $planned = $page->get('summary')['create'];

        $page->call('import');

        $this->assertSame($planned, Product::count());
        $this->assertSame($planned, $page->get('result')['created']);
        $this->assertEqualsWithDelta(189.99, (float) Product::firstWhere('sku', 'TCH-2026')->unit_cost, 0.001);
    }

    public function test_a_second_look_after_importing_shows_nothing_left_to_do(): void
    {
        $file = $this->workbook([['2026 Topps Chrome Hobby Box', 'TCH-2026', 'box', 'BIN', 189.99, 249.99]]);

        $page = $this->page()->set('upload', $file)->call('import');

        $this->assertSame(0, $page->get('summary')['create']);
        $this->assertSame(1, $page->get('summary')['unchanged']);
    }

    public function test_a_formula_in_a_price_cell_is_flagged_rather_than_read_as_zero(): void
    {
        $file = $this->workbook([['2026 Topps Chrome Hobby Box', 'TCH-2026', 'box', 'BIN', '=E2*2', 249.99]]);

        $page = $this->page()->set('upload', $file);
        $rows = $page->get('rows');

        $this->assertNotEmpty($rows[0]['warnings'], 'a formula cell should be reported, not silently skipped');
        $this->assertSame(1, $page->get('summary')['warnings']);
        $this->assertNotContains('Unit cost', array_column($rows[0]['changes'], 'field'));
    }

    public function test_the_same_product_twice_in_one_sheet_is_pointed_out(): void
    {
        $file = $this->workbook([
            ['2026 Topps Chrome Hobby Box', 'TCH-2026', 'box', 'BIN', 189.99, 249.99],
            ['2026 Topps Chrome Hobby Box', 'TCH-2026', 'box', 'BIN', 179.99, 239.99],
        ]);

        $rows = $this->page()->set('upload', $file)->get('rows');

        $this->assertNotEmpty($rows[1]['warnings']);
        $this->assertStringContainsString('line 2', $rows[1]['warnings'][0]);
    }

    public function test_it_offers_the_worksheets_in_the_file_and_reads_the_one_you_pick(): void
    {
        $file = $this->workbook([['2026 Topps Chrome Hobby Box', 'TCH-2026', 'box', 'BIN', 189.99, 249.99]], 'Costs 2026');

        $page = $this->page()->set('upload', $file);

        $this->assertSame(['Costs 2026'], $page->get('sheets'));
        $this->assertSame('Costs 2026', $page->get('sheet'));
        $this->assertSame(1, $page->get('summary')['create']);
    }

    public function test_a_sheet_without_a_product_name_column_says_so_instead_of_importing_nothing(): void
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->setTitle('Product cost ref sheet');
        $spreadsheet->getActiveSheet()->fromArray([['Thing', 'Price'], ['A box', 10]], null, 'A1');
        (new Xlsx($spreadsheet))->save($this->path);

        $page = $this->page()->set('upload', UploadedFile::fake()->createWithContent('wrong.xlsx', file_get_contents($this->path)));

        $this->assertStringContainsString('PRODUCT NAME', (string) $page->get('error'));
        $this->assertSame([], $page->get('rows'));
    }

    public function test_prices_already_set_are_left_alone_unless_overwrite_is_ticked(): void
    {
        Product::create([
            'name'       => '2026 Topps Chrome Hobby Box',
            'sku'        => 'TCH-2026',
            'unit_cost'  => 150.00,
            'sale_price' => 200.00,
            'is_active'  => true,
        ]);

        $file = $this->workbook([['2026 Topps Chrome Hobby Box', 'TCH-2026', 'box', 'BIN', 189.99, 249.99]]);

        $page = $this->page()->set('upload', $file);

        $fields = array_column($page->get('rows')[0]['changes'], 'field');
        $this->assertNotContains('Unit cost', $fields);

        $page->set('overwrite', true);

        $fields = array_column($page->get('rows')[0]['changes'], 'field');
        $this->assertContains('Unit cost', $fields);
        $this->assertSame('150.00', $page->get('rows')[0]['changes'][array_search('Unit cost', $fields, true)]['from']);
    }

    public function test_it_is_not_open_to_everyone(): void
    {
        // Writing to the whole catalogue in one action is an admin job.
        $this->actingAs(User::factory()->create());

        $this->assertFalse(ImportInventorySheet::canAccess());
    }
}
