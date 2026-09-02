<?php

namespace Tests\Feature\AI;

use App\AI\Prompts\PromptLibrary;
use App\AI\Services\AiGateway;
use App\Services\AI\Documents\PalletSlipParser;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ManifestSpreadsheetParserTest extends TestCase
{
    public function test_spreadsheet_manifest_with_known_headers_does_not_call_ai(): void
    {
        $gateway = $this->mock(AiGateway::class);
        $gateway->shouldNotReceive('json');
        $gateway->shouldNotReceive('vision');

        $sheet = new Spreadsheet();
        $sheet->getActiveSheet()->fromArray([
            ['Purchase Order 1234'],
            ['SKU', 'Product Description', 'Cases', 'Unit Cost', 'UPC'],
            ['BOW-26', '2026 Bowman Hobby Box', 12, '$184.50', '123456789012'],
            ['PRZ-26', '2026 Prizm Basketball Hobby', 6, 225.00, '987654321098'],
        ]);

        $path = sys_get_temp_dir() . '/vortex-manifest-' . uniqid() . '.xlsx';
        (new Xlsx($sheet))->save($path);

        $parser = new PalletSlipParser($gateway, app(PromptLibrary::class));
        $lines = $parser->parse($path);

        $this->assertCount(2, $lines);
        $this->assertSame('BOW-26', $lines[0]['sku']);
        $this->assertSame('2026 Bowman Hobby Box', $lines[0]['description']);
        $this->assertSame(12, $lines[0]['case_count']);
        $this->assertSame(184.5, $lines[0]['unit_cost']);
        $this->assertSame('123456789012', $lines[0]['barcode']);
    }
}
