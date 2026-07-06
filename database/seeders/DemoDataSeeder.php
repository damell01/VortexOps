<?php

namespace Database\Seeders;

use App\Models\DeductionRequest;
use App\Models\Pallet;
use App\Models\PalletLine;
use App\Models\Vendor;
use App\Services\ReceivingService;
use App\Models\DeductionRequestLine;
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
use Illuminate\Support\Facades\DB;

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
        $alex = Streamer::firstOrCreate(['name' => 'Alex'], [
            'email'         => 'alex@vortexbreaks.com',
            'payout_type'   => 'hybrid',
            'hourly_rate'   => 18.00,
            'payout_percentage' => 10.00,
            'include_tips'  => true,
            'status'        => 'active',
            'adp_employee_id' => 'ADP-004',
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
        $alexLoc = InventoryLocation::firstOrCreate(['name' => 'Alex Inventory'], [
            'type'        => 'streamer_inventory',
            'streamer_id' => $alex->id,
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
            ['sku' => 'NFL-2025-009', 'name' => '2025 Select Football Hobby Box',  'category' => 'Football',   'unit_cost' => 165.00, 'reorder_level' => 3],
            ['sku' => 'YGO-2024-010', 'name' => 'Yu-Gi-Oh! Phantom Nightmare Box', 'category' => 'TCG',        'unit_cost' => 65.00,  'reorder_level' => 8],
        ];

        $items = [];
        foreach ($itemData as $d) {
            $items[] = InventoryItem::firstOrCreate(
                ['sku' => $d['sku']],
                array_merge($d, ['is_active' => true])
            );
        }
        [$bowman, $topps, $prizm, $optic, $pokemon, $mtg, $bowmanDraft, $hoops, $select, $yugioh] = $items;

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
            [$select,      $mainStorage,  6], [$select,      $alexLoc,    2],
            [$yugioh,      $mainStorage, 18], [$yugioh,      $fulfillment, 5],
        ];

        foreach ($stockData as [$item, $loc, $qty]) {
            if (! $loc) continue;
            InventoryStock::updateOrCreate(
                ['inventory_item_id' => $item->id, 'inventory_location_id' => $loc->id],
                ['quantity' => $qty]
            );
        }

        // ── Movement history ─────────────────────────────────────────────────
        if (InventoryMovement::where('reason', 'Initial stock received')
                ->where('inventory_item_id', $bowman->id)->doesntExist()) {
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
                [$select,  null,        $mainStorage,  8, 'opening',  'Select Football initial stock'],
                [$select,  $mainStorage, $alexLoc,      2, 'transfer', 'Alex stream prep'],
                [$yugioh,  null,        $mainStorage,  23, 'opening', 'Yu-Gi-Oh bulk restock'],
                [$yugioh,  $mainStorage, $fulfillment,  5, 'transfer','Moved to fulfillment'],
            ];
            foreach ($movements as [$item, $from, $to, $qty, $type, $reason]) {
                if (! $to) continue;
                InventoryMovement::create([
                    'inventory_item_id' => $item->id,
                    'from_location_id'  => $from?->id,
                    'to_location_id'    => $to->id,
                    'quantity'          => $qty,
                    'movement_type'     => $type,
                    'reason'            => $reason,
                    'created_by'        => 1,
                ]);
            }
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

        if ($show1->wasRecentlyCreated) {
            InventoryMovement::create([
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
            InventoryMovement::create([
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
        }

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
                'created_by'         => 1,
            ]
        );
        $show3->streamers()->syncWithoutDetaching([$taylor->id => ['is_primary' => true]]);

        // ── Show 4 — Alex on a hybrid show ───────────────────────────────────
        $show4 = Show::firstOrCreate(
            ['title' => 'Mojo Break #40 — Football Frenzy'],
            [
                'whatnot_channel_id' => $channel?->id,
                'show_date'          => Carbon::now()->subDays(5)->toDateString(),
                'start_time'         => '18:00:00',
                'end_time'           => '20:30:00',
                'show_duration'      => 150,
                'units_sold'         => 8,
                'gross_revenue'      => 780.00,
                'whatnot_net'        => 717.60,
                'tips'               => 20.00,
                'import_source'      => 'manual',
                'status'             => 'pending_review',
                'created_by'         => 1,
            ]
        );
        $show4->streamers()->syncWithoutDetaching([
            $alex->id   => ['is_primary' => true],
            $taylor->id => ['is_primary' => false],
        ]);

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

        // ── Historical shows & batches (gives charts 12 weeks of data) ────────
        // Each entry: [weeks_back, title, gross, tips, duration_mins, units, streamer, status]
        $historicalShows = [
            [2,  'Mojo Break #38 — Football Sunday',       980.00,  35.00, 150,  7, $jordan, 'reconciled'],
            [2,  'Mojo Break #39 — Mixed Cards (Alex)',     620.00,  18.00, 120,  5, $alex,   'reconciled'],
            [3,  'Mojo Break #35 — Baseball Weekend',      1100.00,  55.00, 180,  8, $jordan, 'reconciled'],
            [3,  'Mojo Break #36 — TCG Chaos',              440.00,  12.00,  90, 15, $taylor, 'reconciled'],
            [4,  'Mojo Break #31 — Hoops Thursday',         750.00,  22.00, 135,  6, $jordan, 'reconciled'],
            [4,  'Mojo Break #32 — Football with Alex',     540.00,  16.00, 100,  4, $alex,   'reconciled'],
            [5,  'Mojo Break #27 — Baseball Mega',         1450.00,  75.00, 240, 12, $jordan, 'reconciled'],
            [5,  'Mojo Break #28 — TCG Double Header',      310.00,   8.00,  80, 20, $taylor, 'reconciled'],
            [6,  'Mojo Break #23 — Football Night',         890.00,  30.00, 150,  8, $jordan, 'reconciled'],
            [7,  'Mojo Break #19 — Basketball Special',    1200.00,  45.00, 180,  9, $jordan, 'reconciled'],
            [7,  'Mojo Break #20 — TCG Championship',       580.00,  20.00, 120, 20, $taylor, 'reconciled'],
            [8,  'Mojo Break #15 — Baseball Classics',      960.00,  40.00, 165,  7, $jordan, 'reconciled'],
            [9,  'Mojo Break #11 — Mixed Sports',           820.00,  28.00, 135,  6, $alex,   'reconciled'],
            [10, 'Mojo Break #7 — Hoops Night',             740.00,  25.00, 130,  5, $jordan, 'reconciled'],
            [11, 'Mojo Break #3 — Season Opener',          1080.00,  50.00, 195,  9, $taylor, 'reconciled'],
        ];

        foreach ($historicalShows as [$weeksBack, $title, $gross, $tips, $duration, $units, $streamer, $showStatus]) {
            $showDate = Carbon::now()->subWeeks($weeksBack)->startOfWeek(Carbon::MONDAY)->addDays(2);
            $net = round($gross * 0.92, 2);

            $historicShow = Show::firstOrCreate(
                ['title' => $title],
                [
                    'whatnot_channel_id' => $channel?->id,
                    'show_date'          => $showDate->toDateString(),
                    'start_time'         => '19:30:00',
                    'end_time'           => '22:00:00',
                    'show_duration'      => $duration,
                    'units_sold'         => $units,
                    'gross_revenue'      => $gross,
                    'whatnot_net'        => $net,
                    'tips'               => $tips,
                    'import_source'      => 'manual',
                    'status'             => $showStatus,
                    'created_by'         => 1,
                ]
            );
            $historicShow->streamers()->syncWithoutDetaching([$streamer->id => ['is_primary' => true]]);

            // Find or create the batch for this week
            $batchStart = $showDate->copy()->startOfWeek(Carbon::MONDAY);
            $batch = WeeklyPayoutBatch::firstOrCreate(
                ['week_start' => $batchStart->toDateString()],
                [
                    'week_end'     => $batchStart->copy()->endOfWeek(Carbon::SUNDAY)->toDateString(),
                    'status'       => 'paid',
                    'total_payout' => 0,
                    'created_by'   => 1,
                    'finalized_by' => 1,
                    'finalized_at' => $batchStart->copy()->addDays(4),
                ]
            );

            // Calculate payout amount based on streamer type
            $payoutAmount = match ($streamer->payout_type) {
                'profit_share' => round($net * ($streamer->payout_percentage / 100) + $tips, 2),
                'package'      => round(($streamer->package_rate ?? 15) + $tips, 2),
                'hybrid'       => round(($streamer->hourly_rate ?? 18) * ($duration / 60) + ($net * (($streamer->payout_percentage ?? 10) / 100)) + $tips, 2),
                default        => round($net * 0.30 + $tips, 2),
            };

            $payout = Payout::firstOrCreate(
                ['show_id' => $historicShow->id, 'streamer_id' => $streamer->id],
                [
                    'weekly_payout_batch_id' => $batch->id,
                    'payout_type'            => $streamer->payout_type,
                    'gross_show_revenue'     => $gross,
                    'owner_fee_deducted'     => 0,
                    'tips_included'          => $tips,
                    'calculated_payout'      => $payoutAmount,
                    'calculation_notes'      => 'Historical demo payout',
                    'status'                 => 'paid',
                    'created_at'             => $showDate,
                    'updated_at'             => $showDate,
                ]
            );

            // Backdate the payout timestamps so the trends chart shows data spread over time
            if ($payout->wasRecentlyCreated) {
                DB::table('payouts')->where('id', $payout->id)->update([
                    'created_at' => $showDate,
                    'updated_at' => $showDate,
                ]);
            }

            $batch->recalculateTotal();
        }

        // ── Vendor & Pallets ─────────────────────────────────────────────────
        $vendor = Vendor::firstOrCreate(
            ['name' => 'Sports Cards Direct'],
            [
                'contact_name'   => 'Mike Torres',
                'email'          => 'mike@scardsdirect.com',
                'phone'          => '800-555-0100',
                'account_number' => 'SCD-4472',
                'status'         => 'active',
            ]
        );

        $vendor2 = Vendor::firstOrCreate(
            ['name' => 'TCG Wholesale Co.'],
            [
                'contact_name'   => 'Sarah Kim',
                'email'          => 'sarah@tcgwholesale.com',
                'phone'          => '855-555-0200',
                'account_number' => 'TCG-881',
                'status'         => 'active',
            ]
        );

        // Pallet 1 — fully received (demo for completed receiving workflow)
        $pallet = Pallet::firstOrCreate(
            ['reference' => 'PO-2026-001'],
            [
                'vendor_id'     => $vendor->id,
                'received_date' => Carbon::now()->subDays(7)->toDateString(),
                'status'        => 'received',
                'total_cost'    => 1575.00,
                'notes'         => 'Demo pallet — baseball & TCG restocking order',
                'created_by'    => 1,
            ]
        );

        if ($pallet->wasRecentlyCreated) {
            $line1 = PalletLine::create([
                'pallet_id'             => $pallet->id,
                'line_number'           => 1,
                'description'           => '2024 Bowman Chrome Hobby Box',
                'inventory_item_id'     => $bowman->id,
                'inventory_location_id' => $mainStorage?->id,
                'case_count'            => 2,
                'quantity_per_case'     => 6,
                'unit_cost'             => 125.00,
            ]);

            $line2 = PalletLine::create([
                'pallet_id'             => $pallet->id,
                'line_number'           => 2,
                'description'           => 'Pokémon SV Booster Packs (display)',
                'inventory_item_id'     => $pokemon->id,
                'inventory_location_id' => $mainStorage?->id,
                'case_count'            => 3,
                'quantity_per_case'     => 36,
                'unit_cost'             => 4.25,
            ]);

            $receivingService = app(ReceivingService::class);
            $receivingService->receiveAllCasesForLine($line1);
            $receivingService->receiveAllCasesForLine($line2);
        }

        // Pallet 2 — in-progress (for testing the receiving workflow)
        $pallet2 = Pallet::firstOrCreate(
            ['reference' => 'PO-2026-002'],
            [
                'vendor_id'  => $vendor2->id,
                'status'     => 'open',
                'notes'      => 'TCG restock — partially received, Yu-Gi-Oh line pending',
                'created_by' => 1,
            ]
        );

        if ($pallet2->wasRecentlyCreated) {
            $pLine1 = PalletLine::create([
                'pallet_id'             => $pallet2->id,
                'line_number'           => 1,
                'description'           => 'MTG Bloomburrow Set Booster Box',
                'inventory_item_id'     => $mtg->id,
                'inventory_location_id' => $mainStorage?->id,
                'case_count'            => 2,
                'quantity_per_case'     => 6,
                'unit_cost'             => 108.00,
            ]);

            $pLine2 = PalletLine::create([
                'pallet_id'             => $pallet2->id,
                'line_number'           => 2,
                'description'           => 'Yu-Gi-Oh! Phantom Nightmare Box',
                'inventory_item_id'     => $yugioh->id,
                'inventory_location_id' => $mainStorage?->id,
                'case_count'            => 4,
                'quantity_per_case'     => 12,
                'unit_cost'             => 63.00,
            ]);

            // Receive only the first line — leave Yu-Gi-Oh pending
            $receivingService = app(ReceivingService::class);
            $receivingService->receiveAllCasesForLine($pLine1);
        }
    }
}
