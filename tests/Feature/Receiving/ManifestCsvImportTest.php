<?php

namespace Tests\Feature\Receiving;

use App\Models\Pallet;
use App\Models\PalletLine;
use App\Models\Vendor;
use App\Services\ReceivingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Staging a pallet from the vendor's spreadsheet.
 *
 * The lines land unmapped on purpose: nothing is costed into stock here, so a
 * wrong import is deleted rather than unwound.
 */
class ManifestCsvImportTest extends TestCase
{
    use RefreshDatabase;

    private function pallet(): Pallet
    {
        return Pallet::create([
            'vendor_id' => Vendor::create(['name' => 'Acme', 'is_active' => true])->id,
            'po_number' => 'PO-1',
            'status'    => 'staged',
        ]);
    }

    private function csv(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'manifest') . '.csv';
        file_put_contents($path, $contents);

        return $path;
    }

    public function test_a_plain_manifest_becomes_pallet_lines(): void
    {
        $pallet = $this->pallet();

        $result = app(ReceivingService::class)->importManifestCsv($pallet, $this->csv(
            "description,case_count,quantity_per_case,unit_cost\n" .
            "2024 Prizm Hobby,4,12,89.50\n" .
            "2024 Optic Blaster,2,20,24.00\n"
        ));

        $this->assertSame(['imported' => 2, 'skipped' => 0], $result);

        $line = $pallet->lines()->orderBy('line_number')->first();
        $this->assertSame('2024 Prizm Hobby', $line->description);
        $this->assertSame(4, (int) $line->case_count);
        $this->assertEqualsWithDelta(89.50, (float) $line->unit_cost, 0.001);

        // Unmapped until someone says which item it is.
        $this->assertNull($line->inventory_item_id);
    }

    public function test_vendor_header_casing_and_currency_are_tolerated(): void
    {
        // Real vendor exports: title-cased headers, spaces instead of
        // underscores, and dollar signs typed into the cost column. An earlier
        // pass matched headers literally and imported the file as blank lines.
        $pallet = $this->pallet();

        app(ReceivingService::class)->importManifestCsv($pallet, $this->csv(
            "Description,Cases,Units,Cost\n" .
            "2024 Select Mega,3,6,\"\$1,250.00\"\n"
        ));

        $line = $pallet->lines()->first();
        $this->assertSame(3, (int) $line->case_count);
        $this->assertEqualsWithDelta(6, (float) $line->quantity_per_case, 0.001);
        $this->assertEqualsWithDelta(1250.00, (float) $line->unit_cost, 0.001);
    }

    public function test_rows_without_a_description_are_skipped_not_imported(): void
    {
        // Spreadsheets carry blank spacer rows and a totals row; importing them
        // would put nameless lines on the manifest that can never be mapped.
        $pallet = $this->pallet();

        $result = app(ReceivingService::class)->importManifestCsv($pallet, $this->csv(
            "description,case_count,unit_cost\n" .
            "Real Item,1,10\n" .
            ",,\n" .
            "  ,2,5\n"
        ));

        $this->assertSame(['imported' => 1, 'skipped' => 2], $result);
        $this->assertSame(1, $pallet->lines()->count());
    }

    public function test_importing_twice_appends_rather_than_renumbering_from_one(): void
    {
        $pallet = $this->pallet();
        $csv    = "description,case_count,unit_cost\nItem A,1,10\n";

        app(ReceivingService::class)->importManifestCsv($pallet, $this->csv($csv));
        app(ReceivingService::class)->importManifestCsv($pallet, $this->csv($csv));

        $this->assertSame([1, 2], $pallet->lines()->orderBy('line_number')->pluck('line_number')->all());
    }

    public function test_a_missing_quantity_defaults_to_one_rather_than_zero(): void
    {
        // A line of zero units per case receives nothing, so a manifest missing
        // the column would silently stage a pallet that credits no stock.
        $pallet = $this->pallet();

        app(ReceivingService::class)->importManifestCsv($pallet, $this->csv(
            "description\nMystery Box\n"
        ));

        $line = $pallet->lines()->first();
        $this->assertEqualsWithDelta(1, (float) $line->quantity_per_case, 0.001);
        $this->assertSame(1, (int) $line->case_count);
    }

    public function test_a_failed_import_leaves_no_partial_manifest(): void
    {
        $pallet = $this->pallet();
        PalletLine::create([
            'pallet_id'   => $pallet->id,
            'line_number' => 1,
            'description' => 'Already staged by hand',
            'case_count'  => 1,
        ]);

        try {
            app(ReceivingService::class)->importManifestCsv($pallet, '/no/such/file.csv');
        } catch (\Throwable) {
            // The point is what survives, not which exception is thrown.
        }

        $this->assertSame(1, $pallet->lines()->count());
    }
}
