<?php

namespace Tests\Feature\Inventory;

use App\Filament\Resources\ReceivingSessionResource\Pages\ReviewReceivingSession;
use App\Models\InventoryItem;
use App\Models\Pallet;
use App\Models\PalletLine;
use App\Models\ReceivingSession;
use App\Models\User;
use App\Models\Vendor;
use App\Services\ReceivingSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceivingCsvImportTest extends TestCase
{
    use RefreshDatabase;

    // ── The CSV parser (pure) ──────────────────────────────────────────────────

    public function test_parser_skips_header_and_maps_columns(): void
    {
        $csv = "Description,Qty,Unit Cost,UPC\n"
            . "2024 Bowman Chrome Hobby Box,6,125.00,\n"
            . "Pokemon SV Booster,100,4.25,081483012345\n";

        $lines = ReviewReceivingSession::parseManifest($csv);

        $this->assertCount(2, $lines); // header dropped
        $this->assertSame('2024 Bowman Chrome Hobby Box', $lines[0]['description']);
        $this->assertSame(6.0, $lines[0]['qty']);
        $this->assertSame(125.0, $lines[0]['unit_cost']);
        $this->assertNull($lines[0]['upc']);                    // empty cell → null
        $this->assertSame('081483012345', $lines[1]['upc']);    // UPC preserved
    }

    public function test_parser_keeps_a_headerless_file(): void
    {
        // First row is data (qty is numeric) → nothing dropped.
        $lines = ReviewReceivingSession::parseManifest("Widget,3,10.00,\nGadget,1,5.00,\n");
        $this->assertCount(2, $lines);
        $this->assertSame('Widget', $lines[0]['description']);
    }

    public function test_parser_ignores_blank_rows(): void
    {
        $lines = ReviewReceivingSession::parseManifest("Widget,3,10.00\n\n\nGadget,1,5.00\n");
        $this->assertCount(2, $lines);
    }

    // ── Regression: fuzzy catalog must survive repeated matches ────────────────

    public function test_fuzzy_matching_works_across_repeated_calls(): void
    {
        // Force the DB cache store — the old code cached hydrated models here and
        // they came back as __PHP_Incomplete_Class on the second lookup, 500ing.
        config(['cache.default' => 'database']);

        InventoryItem::create(['name' => 'Super Rare Fuzzy Box', 'sku' => 'SRF-1', 'unit_cost' => 50, 'is_active' => true]);

        $matcher = app(\App\Services\ProductMatchingService::class);

        // A description that misses alias/exact and reaches the fuzzy catalog,
        // called twice — the second call previously blew up on cache read-back.
        $matcher->match('Super Rare Fuzzy');
        $second = $matcher->match('Super Rare Fuzzy');

        $this->assertIsArray($second);
        $this->assertArrayHasKey('product', $second);
    }

    // ── Parse → match pipeline end to end ──────────────────────────────────────

    public function test_parsed_lines_run_through_ai_matching(): void
    {
        // One product to auto-match; the other manifest line is unknown.
        InventoryItem::create(['name' => '2024 Bowman Chrome Hobby Box', 'sku' => 'BCH-1', 'unit_cost' => 125, 'is_active' => true]);

        $vendor  = Vendor::create(['name' => 'Acme Cards', 'status' => 'active']);
        $session = ReceivingSession::create([
            'vendor_id'      => $vendor->id,
            'received_by'    => User::factory()->create()->id,
            'invoice_number' => 'INV-1',
            'status'         => 'pending',
        ]);
        $pallet = Pallet::create([
            'vendor_id'            => $vendor->id,
            'receiving_session_id' => $session->id,
            'reference'            => 'S1',
            'status'               => 'receiving',
            'created_by'           => $session->received_by,
        ]);

        $csv = "Description,Qty,Unit Cost,UPC\n"
            . "2024 Bowman Chrome Hobby Box,6,125.00,\n"
            . "2025 Panini Mosaic Soccer Hobby Box,3,160.00,\n";

        $lines = ReviewReceivingSession::parseManifest($csv);
        app(ReceivingSessionService::class)->importLines($session, $pallet, $lines);

        $palletLines = PalletLine::where('pallet_id', $pallet->id)->get();
        $this->assertCount(2, $palletLines);

        $bowman = $palletLines->first(fn ($l) => str_contains((string) $l->vendor_description, 'Bowman'));
        $mosaic = $palletLines->first(fn ($l) => str_contains((string) $l->vendor_description, 'Mosaic'));

        $this->assertNotNull($bowman?->inventory_item_id, 'Exact-name line should auto-match to inventory');
        $this->assertNull($mosaic?->inventory_item_id, 'Unknown product should be left for review/create');
    }
}
