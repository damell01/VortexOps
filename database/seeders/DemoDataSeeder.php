<?php

namespace Database\Seeders;

use App\Models\DeductionRequest;
use App\Models\DeductionRequestLine;
use App\Models\InventoryContainer;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\Payout;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\WeeklyPayoutBatch;
use App\Models\WhatnotChannel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        // ── Streamers ────────────────────────────────────────────────────────
        $jordan = Streamer::firstOrCreate(['name' => 'Jordan'], [
            'email'             => 'jordan@vortexbreaks.com',
            'payout_type'       => 'profit_share',
            'payout_percentage' => 35.00,
            'include_tips'      => true,
            'status'            => 'active',
            'adp_employee_id'   => 'ADP-001',
        ]);
        $taylor = Streamer::firstOrCreate(['name' => 'Taylor'], [
            'email'           => 'taylor@vortexbreaks.com',
            'payout_type'     => 'package',
            'package_rate'    => 15.00,
            'include_tips'    => true,
            'status'          => 'active',
            'adp_employee_id' => 'ADP-002',
        ]);
        $morgan = Streamer::firstOrCreate(['name' => 'Morgan'], [
            'email'        => 'morgan@vortexbreaks.com',
            'payout_type'  => 'hourly',
            'hourly_rate'  => 22.50,
            'include_tips' => false,
            'status'       => 'on_leave',
        ]);

        // ── Locations ────────────────────────────────────────────────────────
        $mainStorage = InventoryLocation::where('name', 'Main Storage')->first();
        $returnedLoc = InventoryLocation::where('name', 'Returned Inventory')->first();
        $damagedLoc  = InventoryLocation::where('name', 'Damaged Inventory')->first();
        $fulfillment = InventoryLocation::where('name', 'Fulfillment Area')->first();

        $jordanLoc = InventoryLocation::firstOrCreate(['name' => 'Jordan Inventory'], [
            'type'        => 'streamer_inventory',
            'streamer_id' => $jordan->id,
            'status'      => 'active',
        ]);
        $taylorLoc = InventoryLocation::firstOrCreate(['name' => 'Taylor Inventory'], [
            'type'        => 'streamer_inventory',
            'streamer_id' => $taylor->id,
            'status'      => 'active',
        ]);

        // ── Inventory items ──────────────────────────────────────────────────
        $itemData = [
            ['sku' => 'BCH-2024-001', 'name' => '2024 Bowman Chrome Hobby Box',    'category' => 'Baseball',   'unit_cost' => 125.00, 'reorder_level' => 5],
            ['sku' => 'TPS-2024-002', 'name' => '2024 Topps Series 1 Hobby Box',   'category' => 'Baseball',   'unit_cost' => 95.00,  'reorder_level' => 8],
            ['sku' => 'PRI-2024-003', 'name' => '2024 Prizm Basketball Hobby Box', 'category' => 'Basketball', 'unit_cost' => 185.00, 'reorder_level' => 3],
            ['sku' => 'OPT-2024-004', 'name' => '2024 Donruss Optic Football Box', 'category' => 'Football',   'unit_cost' => 145.00, 'reorder_level' => 4],
            ['sku' => 'PKM-2024-005', 'name' => 'Pokémon SV Booster Pack',         'category' => 'TCG',        'unit_cost' => 4.50,   'reorder_level' => 50],
            ['sku' => 'MTG-2024-006', 'name' => 'MTG Bloomburrow Set Booster Box', 'category' => 'TCG',        'unit_cost' => 110.00, 'reorder_level' => 6],
            ['sku' => 'SCR-2025-007', 'name' => '2025 Bowman Draft HTA Box',       'category' => 'Baseball',   'unit_cost' => 210.00, 'reorder_level' => 2],
            ['sku' => 'NBA-2024-008', 'name' => '2024 Hoops Basketball Blaster',   'category' => 'Basketball', 'unit_cost' => 22.00,  'reorder_level' => 20],
        ];

        $items = [];
        foreach ($itemData as $d) {
            $items[] = InventoryItem::firstOrCreate(
                ['sku' => $d['sku']],
                array_merge($d, ['is_active' => true, 'notes' => 'Demo inventory item for show and deduction workflow testing.'])
            );
        }
        [$bowman, $topps, $prizm, $optic, $pokemon, $mtg, $bowmanDraft, $hoops] = $items;

        // ── Stock ────────────────────────────────────────────────────────────
        $stockData = [
            [$bowman,      $mainStorage,  8], [$bowman,      $jordanLoc,  4],
            [$topps,       $mainStorage, 22], [$topps,       $taylorLoc,  3],
            [$prizm,       $mainStorage,  5], [$prizm,       $jordanLoc,  2],
            [$optic,       $mainStorage, 13], [$optic,       $taylorLoc,  1],
            [$pokemon,     $mainStorage, 110],[$pokemon,     $fulfillment, 30],
            [$mtg,         $mainStorage,  7],
            [$bowmanDraft, $mainStorage,  1],
            [$hoops,       $mainStorage, 41], [$hoops,       $returnedLoc, 3],
        ];

        foreach ($stockData as [$item, $loc, $qty]) {
            if (! $loc) continue;
            InventoryStock::updateOrCreate(
                ['inventory_item_id' => $item->id, 'inventory_location_id' => $loc->id],
                ['quantity' => $qty]
            );
        }

        // ── Movement history ─────────────────────────────────────────────────
        $movements = [
            [$bowman,  null,        $mainStorage, 12, 'opening',  'Initial stock received'],
            [$bowman,  $mainStorage, $jordanLoc,   4, 'transfer', 'Transferred to Jordan for stream'],
            [$topps,   null,        $mainStorage, 25, 'opening',  'Initial stock received'],
            [$topps,   $mainStorage, $taylorLoc,   3, 'transfer', 'Transferred to Taylor'],
            [$prizm,   null,        $mainStorage,  7, 'opening',  'Opening inventory'],
            [$prizm,   $mainStorage, $jordanLoc,   2, 'transfer', 'Jordan stream prep'],
            [$pokemon, null,        $mainStorage, 140,'opening',  'Bulk Pokémon restock'],
            [$pokemon, $mainStorage, $fulfillment, 30, 'transfer','Moved to fulfillment'],
            [$hoops,   $mainStorage, $returnedLoc,  3, 'return',  'Customer returns processed'],
        ];

        foreach ($movements as [$item, $from, $to, $qty, $type, $reason]) {
            if (! $to) continue;
            InventoryMovement::firstOrCreate([
                'inventory_item_id' => $item->id,
                'from_location_id'  => $from?->id,
                'to_location_id'    => $to->id,
                'quantity'          => $qty,
                'movement_type'     => $type,
                'reason'            => $reason,
                'created_by'        => 1,
            ]);
        }

        // ── Whatnot channel ──────────────────────────────────────────────────
        $channel = WhatnotChannel::where('name', 'Vortex Main Channel')->first();

        // ── Show 1 — Fully reconciled (processed deduction + executed lines) ─
        $show1 = Show::firstOrCreate(
            ['title' => 'Mojo Break #41 — Baseball Night'],
            [
                'whatnot_channel_id' => $channel?->id,
                'show_date'          => Carbon::now()->subDays(14)->toDateString(),
                'start_time'         => '19:00:00',
                'end_time'           => '22:30:00',
                'show_duration'      => 210,
                'units_sold'         => 6,
                'gross_revenue'      => 1240.00,
                'whatnot_net'        => 1140.80,
                'tips'               => 42.00,
                'import_source'      => 'manual',
                'status'             => 'reconciled',
                'notes'              => 'Demo show with approved sold-item deductions and completed payout math.',
                'created_by'         => 1,
            ]
        );
        $show1->streamers()->syncWithoutDetaching([$jordan->id => ['is_primary' => true]]);

        $req1 = DeductionRequest::firstOrCreate(
            ['show_id' => $show1->id, 'streamer_id' => $jordan->id],
            [
                'status'           => 'processed',
                'ai_mapping_notes' => 'AI matched 2 line items with high confidence based on show title and streamer inventory.',
                'approved_by'      => 1,
                'approved_at'      => Carbon::now()->subDays(13),
                'processed_by'     => 1,
                'processed_at'     => Carbon::now()->subDays(13),
            ]
        );

        DeductionRequestLine::firstOrCreate(
            ['deduction_request_id' => $req1->id, 'inventory_item_id' => $bowman->id],
            [
                'inventory_location_id' => $jordanLoc->id,
                'quantity_suggested'    => 4,
                'quantity_approved'     => 4,
                'unit_cost_snapshot'    => 125.00,
                'line_total'            => 500.00,
                'raw_description'       => '4x 2024 Bowman Chrome Hobby Box',
                'ai_confidence'         => 'high',
                'ai_reason'             => 'Title mentions "Baseball Night", Jordan inventory has Bowman boxes',
            ]
        );
        DeductionRequestLine::firstOrCreate(
            ['deduction_request_id' => $req1->id, 'inventory_item_id' => $topps->id],
            [
                'inventory_location_id' => $taylorLoc->id,
                'quantity_suggested'    => 2,
                'quantity_approved'     => 2,
                'unit_cost_snapshot'    => 95.00,
                'line_total'            => 190.00,
                'raw_description'       => '2x 2024 Topps Series 1',
                'ai_confidence'         => 'high',
                'ai_reason'             => 'SKU matches exactly',
            ]
        );

        // Record sale_deduction movements for show 1
        InventoryMovement::firstOrCreate([
            'inventory_item_id' => $bowman->id,
            'from_location_id'  => $jordanLoc->id,
            'to_location_id'    => null,
            'quantity'          => 4,
            'movement_type'     => 'sale_deduction',
            'reason'            => 'Approved deduction for show #' . $show1->id,
            'reference_type'    => 'deduction_request',
            'reference_id'      => $req1->id,
            'created_by'        => 1,
        ]);
        InventoryMovement::firstOrCreate([
            'inventory_item_id' => $topps->id,
            'from_location_id'  => $taylorLoc->id,
            'to_location_id'    => null,
            'quantity'          => 2,
            'movement_type'     => 'sale_deduction',
            'reason'            => 'Approved deduction for show #' . $show1->id,
            'reference_type'    => 'deduction_request',
            'reference_id'      => $req1->id,
            'created_by'        => 1,
        ]);

        // ── Show 2 — Pending approval (AI mapping complete, awaiting ops) ────
        $show2 = Show::firstOrCreate(
            ['title' => 'Mojo Break #42 — Hoops & Football'],
            [
                'whatnot_channel_id'     => $channel?->id,
                'show_date'              => Carbon::now()->subDays(3)->toDateString(),
                'start_time'             => '20:00:00',
                'end_time'               => '23:00:00',
                'show_duration'          => 180,
                'units_sold'             => 9,
                'gross_revenue'          => 960.00,
                'whatnot_net'            => 883.20,
                'tips'                   => 28.00,
                'import_source'          => 'manual',
                'status'                 => 'pending_approval',
                'notes'                  => 'Demo show awaiting ops approval of AI-suggested sold inventory items.',
                'ai_streamer_suggestion' => [
                    ['streamer_id' => $jordan->id, 'streamer_name' => 'Jordan', 'confidence' => 'high', 'reason' => 'Title matches Jordan\'s Hoops series'],
                    ['streamer_id' => $taylor->id, 'streamer_name' => 'Taylor', 'confidence' => 'medium', 'reason' => 'Taylor also does Football breaks'],
                ],
                'created_by'             => 1,
            ]
        );
        $show2->streamers()->syncWithoutDetaching([
            $jordan->id => ['is_primary' => true],
            $taylor->id => ['is_primary' => false],
        ]);

        $req2 = DeductionRequest::firstOrCreate(
            ['show_id' => $show2->id, 'streamer_id' => $jordan->id],
            [
                'status'           => 'pending',
                'ai_mapping_notes' => 'AI identified 3 line items. Prizm boxes are high confidence; Optic and Hoops are medium — qty may need ops review.',
            ]
        );

        DeductionRequestLine::firstOrCreate(
            ['deduction_request_id' => $req2->id, 'inventory_item_id' => $prizm->id],
            [
                'inventory_location_id' => $jordanLoc->id,
                'quantity_suggested'    => 3,
                'quantity_approved'     => 3,
                'unit_cost_snapshot'    => 185.00,
                'line_total'            => 555.00,
                'raw_description'       => '3x Prizm Basketball Hobby',
                'ai_confidence'         => 'high',
                'ai_reason'             => 'Prizm is Jordan\'s signature product; exact qty from gross revenue match',
            ]
        );
        DeductionRequestLine::firstOrCreate(
            ['deduction_request_id' => $req2->id, 'inventory_item_id' => $optic->id],
            [
                'inventory_location_id' => $taylorLoc->id,
                'quantity_suggested'    => 2,
                'quantity_approved'     => 2,
                'unit_cost_snapshot'    => 145.00,
                'line_total'            => 290.00,
                'raw_description'       => 'Optic Football box x2',
                'ai_confidence'         => 'medium',
                'ai_reason'             => 'Football mentioned in title; Optic is highest-cost football product in Taylor inventory',
            ]
        );
        DeductionRequestLine::firstOrCreate(
            ['deduction_request_id' => $req2->id, 'inventory_item_id' => $hoops->id],
            [
                'inventory_location_id' => $mainStorage->id,
                'quantity_suggested'    => 4,
                'quantity_approved'     => 4,
                'unit_cost_snapshot'    => 22.00,
                'line_total'            => 88.00,
                'raw_description'       => 'Hoops blasters x4',
                'ai_confidence'         => 'low',
                'ai_reason'             => 'Title mentions Hoops but qty uncertain — recommend ops verification',
            ]
        );

        // ── Show 3 — Pending review (streamer assigned, no mapping yet) ──────
        $show3 = Show::firstOrCreate(
            ['title' => 'Mojo Break #43 — TCG Night'],
            [
                'whatnot_channel_id' => $channel?->id,
                'show_date'          => Carbon::now()->toDateString(),
                'units_sold'         => 12,
                'gross_revenue'      => 350.00,
                'whatnot_net'        => 322.00,
                'tips'               => 15.00,
                'import_source'      => 'manual',
                'status'             => 'pending_review',
                'notes'              => 'Demo show ready for manual sold-item selection and deduction review.',
                'created_by'         => 1,
            ]
        );
        $show3->streamers()->syncWithoutDetaching([$taylor->id => ['is_primary' => true]]);

        // ── Payouts ──────────────────────────────────────────────────────────

        $batch1 = WeeklyPayoutBatch::firstOrCreate(
            ['week_start' => Carbon::now()->subDays(14)->startOfWeek()->toDateString()],
            [
                'week_end'     => Carbon::now()->subDays(14)->endOfWeek()->toDateString(),
                'status'       => 'paid',
                'total_payout' => 357.28,
                'created_by'   => 1,
                'finalized_by' => 1,
                'finalized_at' => Carbon::now()->subDays(11),
            ]
        );

        Payout::firstOrCreate(
            ['show_id' => $show1->id, 'streamer_id' => $jordan->id],
            [
                'weekly_payout_batch_id' => $batch1->id,
                'payout_type'            => 'profit_share',
                'gross_show_revenue'     => 1140.80,
                'owner_fee_deducted'     => 114.08,
                'tips_included'          => 42.00,
                'calculated_payout'      => 357.28,
                'calculation_notes'      => 'Profit share 35% of $1,026.72 + $42.00 tips',
                'status'                 => 'paid',
            ]
        );

        $batch2 = WeeklyPayoutBatch::firstOrCreate(
            ['week_start' => Carbon::now()->subDays(3)->startOfWeek()->toDateString()],
            [
                'week_end'     => Carbon::now()->subDays(3)->endOfWeek()->toDateString(),
                'status'       => 'draft',
                'total_payout' => 0,
                'created_by'   => 1,
            ]
        );

        Payout::firstOrCreate(
            ['show_id' => $show2->id, 'streamer_id' => $jordan->id],
            [
                'weekly_payout_batch_id' => $batch2->id,
                'payout_type'            => 'profit_share',
                'gross_show_revenue'     => 883.20,
                'owner_fee_deducted'     => 88.32,
                'tips_included'          => 14.00,
                'calculated_payout'      => 278.57,
                'calculation_notes'      => 'Profit share 35% of $794.88 / 2 streamers + $14.00 tips',
                'status'                 => 'draft',
            ]
        );
        Payout::firstOrCreate(
            ['show_id' => $show2->id, 'streamer_id' => $taylor->id],
            [
                'weekly_payout_batch_id' => $batch2->id,
                'payout_type'            => 'package',
                'gross_show_revenue'     => 883.20,
                'owner_fee_deducted'     => 0,
                'tips_included'          => 14.00,
                'calculated_payout'      => 29.00,
                'calculation_notes'      => 'Package rate $15.00 + $14.00 tips',
                'status'                 => 'draft',
            ]
        );

        $batch2->recalculateTotal();

        $this->seedDemoContainers(
            bowman: $bowman,
            pokemon: $pokemon,
            mainStorage: $mainStorage,
            fulfillment: $fulfillment
        );
    }

    private function seedDemoContainers(
        InventoryItem $bowman,
        InventoryItem $pokemon,
        ?InventoryLocation $mainStorage,
        ?InventoryLocation $fulfillment,
    ): void {
        if (! InventoryContainer::schemaReady() || ! $mainStorage) {
            return;
        }

        $receiving = InventoryLocation::firstOrCreate(['name' => 'Demo Receiving Bay'], [
            'type' => 'receiving',
            'status' => 'active',
            'notes' => 'Demo receiving location for pallet and case intake walkthroughs.',
        ]);

        $bowmanPallet = InventoryContainer::firstOrCreate(['label' => 'DEMO-PAL-BCH-001'], [
            'inventory_item_id' => $bowman->id,
            'inventory_location_id' => $receiving->id,
            'container_type' => 'pallet',
            'barcode' => 'DEMO-PAL-BCH-001',
            'quantity' => 8,
            'status' => 'active',
            'scanner_ready' => true,
            'notes' => 'Demo inbound pallet before breakdown into sellable cases.',
        ]);

        InventoryContainer::firstOrCreate(['label' => 'DEMO-CASE-BCH-001'], [
            'inventory_item_id' => $bowman->id,
            'inventory_location_id' => $mainStorage->id,
            'parent_container_id' => $bowmanPallet->id,
            'container_type' => 'case',
            'barcode' => 'DEMO-CASE-BCH-001',
            'quantity' => 4,
            'status' => 'active',
            'scanner_ready' => true,
            'notes' => 'Demo sellable case after pallet breakdown.',
        ]);

        InventoryContainer::firstOrCreate(['label' => 'DEMO-CASE-BCH-002'], [
            'inventory_item_id' => $bowman->id,
            'inventory_location_id' => $mainStorage->id,
            'parent_container_id' => $bowmanPallet->id,
            'container_type' => 'case',
            'barcode' => 'DEMO-CASE-BCH-002',
            'quantity' => 4,
            'status' => 'active',
            'scanner_ready' => true,
            'notes' => 'Second demo case ready to assign to a location.',
        ]);

        if ($fulfillment) {
            $pokemonPallet = InventoryContainer::firstOrCreate(['label' => 'DEMO-PAL-PKM-001'], [
                'inventory_item_id' => $pokemon->id,
                'inventory_location_id' => $receiving->id,
                'container_type' => 'pallet',
                'barcode' => 'DEMO-PAL-PKM-001',
                'quantity' => 60,
                'status' => 'active',
                'scanner_ready' => true,
                'notes' => 'Demo TCG pallet used to illustrate receiving and putaway.',
            ]);

            InventoryContainer::firstOrCreate(['label' => 'DEMO-CASE-PKM-001'], [
                'inventory_item_id' => $pokemon->id,
                'inventory_location_id' => $fulfillment->id,
                'parent_container_id' => $pokemonPallet->id,
                'container_type' => 'case',
                'barcode' => 'DEMO-CASE-PKM-001',
                'quantity' => 30,
                'status' => 'active',
                'scanner_ready' => true,
                'notes' => 'Demo TCG case already put away to fulfillment.',
            ]);
        }
    }
}
