<?php

namespace Tests\Feature\Inventory;

use App\Services\ProductSheetImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ProductSheetImporterBlankRowsTest extends TestCase
{
    use RefreshDatabase;

    public function test_real_sheet_layout_uses_first_column_as_product_name_and_ignores_blank_rows(): void
    {
        $path = storage_path('framework/testing/product-sheet-empty-rows-' . uniqid() . '.xlsx');

        try {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle(ProductSheetImporter::DEFAULT_SHEET);

            // Mirrors the real Product cost ref sheet: Product Name is column A,
            // there are blank spacer rows, and the accounting columns to the
            // right contain formulas that are intentionally NOT inventory input.
            $sheet->fromArray([
                ['PRODUCT NAME', 'SKU', 'Type', 'Auction or BIN?', 'Cost', 'Sale price / Target', 'Margin', 'QTY', 'TOTAL COST', 'TOTAL SALES', 'TOTAL MARGIN', 'Notes'],
                ['2025 Topps Galaxy Soccer Value Box', '25TGLXYSOCVAL', 'box', 'BIN', 24.65, 44.00, '=F2-E2', null, '=H2*E2', '=H2*F2', '=J2-I2', null],
                ['2024 Topps Heritage High Number', '24TCHERITAGEHN', 'box', 'BIN', 67.53, 89.99, '=F3-E3', null, '=H3*E3', '=H3*F3', '=J3-I3', null],
                [null, null, null, null, null, null, null, null, null, null, null, null],
                ['2023 Panini Select WWE Blaster', '23WWESTLSWBLST', 'box', 'BIN', 19.76, 25.00, '=F5-E5', null, '=H5*E5', '=H5*F5', '=J5-I5', null],
                ['2023 WWE Panini Donruss Elite Hobby Box', '2023WWEDONELTHOBBY', 'box', 'BIN', 79.98, 125.00, '=F6-E6', null, '=H6*E6', '=H6*F6', '=J6-I6', null],
                ['   ', '   ', null, null, null, null, null, null, null, null, null, null],
            ], null, 'A1');

            (new Xlsx($spreadsheet))->save($path);

            $rows = app(ProductSheetImporter::class)->read($path, ProductSheetImporter::DEFAULT_SHEET);

            $this->assertCount(4, $rows);
            $this->assertSame([
                '2025 Topps Galaxy Soccer Value Box',
                '2024 Topps Heritage High Number',
                '2023 Panini Select WWE Blaster',
                '2023 WWE Panini Donruss Elite Hobby Box',
            ], array_column($rows, 'name'));
            $this->assertSame([
                '25TGLXYSOCVAL',
                '24TCHERITAGEHN',
                '23WWESTLSWBLST',
                '2023WWEDONELTHOBBY',
            ], array_column($rows, 'sku'));
            $this->assertSame([2, 3, 5, 6], array_column($rows, 'line'));
            $this->assertSame([24.65, 67.53, 19.76, 79.98], array_column($rows, 'cost'));
        } finally {
            if (isset($spreadsheet)) {
                $spreadsheet->disconnectWorksheets();
            }
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}
