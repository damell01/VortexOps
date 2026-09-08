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

    public function test_completely_empty_and_whitespace_only_rows_are_ignored(): void
    {
        $path = storage_path('framework/testing/product-sheet-empty-rows-' . uniqid() . '.xlsx');

        try {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle(ProductSheetImporter::DEFAULT_SHEET);
            $sheet->fromArray([
                ['PRODUCT NAME', 'SKU', 'Type', 'Auction or BIN?', 'Cost', 'Sale price / Target'],
                ['2026 Topps Chrome Hobby Box', 'TCH-2026', 'box', 'BIN', 189.99, 249.99],
                [null, null, null, null, null, null],
                ['   ', '   ', null, null, null, null],
                ['2026 Prizm Football Blaster', 'PZF-2026', 'box', 'Auction', 24.50, 39.99],
                [null, null, null, null, null, null],
            ], null, 'A1');

            (new Xlsx($spreadsheet))->save($path);

            $rows = app(ProductSheetImporter::class)->read($path, ProductSheetImporter::DEFAULT_SHEET);

            $this->assertCount(2, $rows);
            $this->assertSame(['TCH-2026', 'PZF-2026'], array_column($rows, 'sku'));
            $this->assertSame([2, 5], array_column($rows, 'line'));
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
