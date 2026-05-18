<?php

namespace Database\Seeders;

use App\Models\DeductionRequest;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\Payout;
use App\Models\ShowFinancial;
use App\Models\ShowSale;
use App\Models\Streamer;
use App\Models\WeeklyPayoutBatch;
use App\Models\WhatnotChannel;
use App\Models\WhatnotShow;
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
            'email'        => 'taylor@vortexbreaks.com',
            'payout_type'  => 'package',
            'package_rate' => 15.00,
            'include_tips' => true,
            'status'       => 'active',
            'adp_employee_id' => 'ADP-002',
        ]);
        $morgan = Streamer::firstOrCreate(['name' => 'Morgan'], [
            'email'       => 'morgan@vortexbreaks.com',
            'payout_type' => 'hourly',
            'hourly_rate' => 22.50,
            'include_tips'=> false,
            'status'      => 'on_leave',
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

        // ── Inventory items ───────────────────────────────────────────────────
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
                array_merge($d, ['is_active' => true])
            );
        }
        [$bowman, $topps, $prizm, $optic, $pokemon, $mtg, $bowmanDraft, $hoops] = $items;

        // ── Stock ─────────────────────────────────────────────────────────────
        $stockData = [
            [$bowman,      $mainStorage, 12], [$bowman,      $jordanLoc,  6],
            [$topps,       $mainStorage, 24], [$topps,       $taylorLoc,  3],
            [$prizm,       $mainStorage,  8], [$prizm,       $jordanLoc,  2],
            [$optic,       $mainStorage, 15], [$optic,       $taylorLoc,  1],
            [$pokemon,     $mainStorage, 120],[$pokemon,     $fulfillment,30],
            [$mtg,         $mainStorage,  7],
            [$bowmanDraft, $mainStorage,  1],
            [$hoops,       $mainStorage, 45], [$hoops,       $returnedLoc, 3],
        ];

        foreach ($stockData as [$item, $loc, $qty]) {
            if (!$loc) continue;
            InventoryStock::updateOrCreate(
                ['inventory_item_id' => $item->id, 'inventory_location_id' => $loc->id],
                ['quantity' => $qty]
            );
        }

        // ── Movement history ──────────────────────────────────────────────────
        $movements = [
            [$bowman,  null,        $mainStorage, 18, 'opening',  'Initial stock received'],
            [$bowman,  $mainStorage,$jordanLoc,    6, 'transfer', 'Transferred to Jordan for stream'],
            [$topps,   null,        $mainStorage, 27, 'opening',  'Initial stock received'],
            [$topps,   $mainStorage,$taylorLoc,    3, 'transfer', 'Transferred to Taylor'],
            [$prizm,   null,        $mainStorage,  8, 'opening',  'Opening inventory'],
            [$prizm,   $mainStorage,$jordanLoc,    2, 'transfer', 'Jordan stream prep'],
            [$pokemon, null,        $mainStorage,150, 'opening',  'Bulk Pokémon restock'],
            [$pokemon, $mainStorage,$fulfillment,  30, 'transfer','Moved to fulfillment'],
            [$hoops,   $mainStorage,$returnedLoc,   3, 'return',  'Customer returns processed'],
        ];

        foreach ($movements as [$item, $from, $to, $qty, $type, $reason]) {
            if (!$to) continue;
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

        // ── Whatnot channel ───────────────────────────────────────────────────
        $channel = WhatnotChannel::where('name', 'Vortex Main Channel')->first();

        // ── Shows ─────────────────────────────────────────────────────────────

        // Show 1 — fully reconciled, paid out
        $show1 = WhatnotShow::firstOrCreate(
            ['title' => 'Mojo Break #41 — Baseball Night'],
            [
                'whatnot_channel_id' => $channel?->id,
                'show_date'          => Carbon::now()->subDays(14)->toDateString(),
                'started_at'         => Carbon::now()->subDays(14)->setTime(19, 0),
                'ended_at'           => Carbon::now()->subDays(14)->setTime(22, 30),
                'status'             => 'reconciled',
                'source'             => 'manual',
                'created_by'         => 1,
            ]
        );
        $show1->streamers()->syncWithoutDetaching([$jordan->id => ['is_primary' => true]]);

        ShowFinancial::updateOrCreate(['whatnot_show_id' => $show1->id], [
            'gross_sales'            => 1240.00,
            'platform_fee_pct'       => 8.00,
            'platform_fee_amount'    => 99.20,
            'shipping_collected'     => 85.00,
            'tips_collected'         => 42.00,
            'owner_platform_fee_pct' => 10.00,
            'net_revenue'            => 1140.80,
            'cogs'                   => 625.00,
            'gross_profit'           => 515.80,
        ]);

        $sale1a = ShowSale::firstOrCreate(
            ['whatnot_show_id' => $show1->id, 'order_id' => 'WN-10001'],
            [
                'inventory_item_id' => $bowman->id,
                'item_name'         => '2024 Bowman Chrome Hobby Box',
                'sku'               => 'BCH-2024-001',
                'quantity'          => 4,
                'sale_price'        => 165.00,
                'buyer_username'    => 'cardkid_88',
                'sale_type'         => 'break_slot',
                'sold_at'           => Carbon::now()->subDays(14)->setTime(19, 45),
            ]
        );
        $sale1b = ShowSale::firstOrCreate(
            ['whatnot_show_id' => $show1->id, 'order_id' => 'WN-10002'],
            [
                'inventory_item_id' => $topps->id,
                'item_name'         => '2024 Topps Series 1 Hobby Box',
                'sku'               => 'TPS-2024-002',
                'quantity'          => 2,
                'sale_price'        => 130.00,
                'buyer_username'    => 'baseballbreaks',
                'sale_type'         => 'break_slot',
                'sold_at'           => Carbon::now()->subDays(14)->setTime(20, 15),
            ]
        );

        // Deduction requests for show 1 — executed
        foreach ([
            [$sale1a, $bowman, $jordanLoc, 4, 125.00],
            [$sale1b, $topps,  $taylorLoc, 2, 95.00],
        ] as [$sale, $item, $loc, $qty, $cost]) {
            DeductionRequest::firstOrCreate(
                ['show_sale_id' => $sale->id],
                [
                    'whatnot_show_id'       => $show1->id,
                    'inventory_item_id'     => $item->id,
                    'inventory_location_id' => $loc->id,
                    'quantity'              => $qty,
                    'unit_cost'             => $cost,
                    'status'                => 'executed',
                    'reviewed_by'           => 1,
                    'reviewed_at'           => Carbon::now()->subDays(13),
                ]
            );
        }

        // Show 2 — last week, pending reconciliation
        $show2 = WhatnotShow::firstOrCreate(
            ['title' => 'Mojo Break #42 — Hoops & Football'],
            [
                'whatnot_channel_id' => $channel?->id,
                'show_date'          => Carbon::now()->subDays(5)->toDateString(),
                'started_at'         => Carbon::now()->subDays(5)->setTime(20, 0),
                'ended_at'           => Carbon::now()->subDays(5)->setTime(23, 0),
                'status'             => 'pending_reconciliation',
                'source'             => 'manual',
                'created_by'         => 1,
            ]
        );
        $show2->streamers()->syncWithoutDetaching([
            $jordan->id => ['is_primary' => true],
            $taylor->id => ['is_primary' => false],
        ]);

        ShowFinancial::updateOrCreate(['whatnot_show_id' => $show2->id], [
            'gross_sales'            => 960.00,
            'platform_fee_pct'       => 8.00,
            'platform_fee_amount'    => 76.80,
            'shipping_collected'     => 60.00,
            'tips_collected'         => 28.00,
            'owner_platform_fee_pct' => 10.00,
            'net_revenue'            => 883.20,
            'cogs'                   => 0,
            'gross_profit'           => 0,
        ]);

        $sale2a = ShowSale::firstOrCreate(
            ['whatnot_show_id' => $show2->id, 'order_id' => 'WN-10010'],
            [
                'inventory_item_id' => $prizm->id,
                'item_name'         => '2024 Prizm Basketball Hobby Box',
                'sku'               => 'PRI-2024-003',
                'quantity'          => 3,
                'sale_price'        => 240.00,
                'buyer_username'    => 'hoopsking',
                'sale_type'         => 'break_slot',
                'sold_at'           => Carbon::now()->subDays(5)->setTime(20, 30),
            ]
        );
        $sale2b = ShowSale::firstOrCreate(
            ['whatnot_show_id' => $show2->id, 'order_id' => 'WN-10011'],
            [
                'inventory_item_id' => $optic->id,
                'item_name'         => '2024 Donruss Optic Football Box',
                'sku'               => 'OPT-2024-004',
                'quantity'          => 2,
                'sale_price'        => 195.00,
                'buyer_username'    => 'gridiron_breaks',
                'sale_type'         => 'break_slot',
                'sold_at'           => Carbon::now()->subDays(5)->setTime(21, 10),
            ]
        );
        $sale2c = ShowSale::firstOrCreate(
            ['whatnot_show_id' => $show2->id, 'order_id' => null],
            [
                // Unmatched — no inventory_item_id, tests AI matching flow
                'inventory_item_id' => null,
                'item_name'         => 'Hoops Blaster Box x4',
                'sku'               => null,
                'quantity'          => 4,
                'sale_price'        => 30.00,
                'buyer_username'    => 'blasterking',
                'sale_type'         => 'fixed_price',
                'sold_at'           => Carbon::now()->subDays(5)->setTime(22, 0),
            ]
        );

        // Pending deductions for show 2
        foreach ([
            [$sale2a, $prizm, $jordanLoc, 3, 185.00],
            [$sale2b, $optic, $taylorLoc, 2, 145.00],
        ] as [$sale, $item, $loc, $qty, $cost]) {
            DeductionRequest::firstOrCreate(
                ['show_sale_id' => $sale->id],
                [
                    'whatnot_show_id'       => $show2->id,
                    'inventory_item_id'     => $item->id,
                    'inventory_location_id' => $loc->id,
                    'quantity'              => $qty,
                    'unit_cost'             => $cost,
                    'status'                => 'pending',
                ]
            );
        }

        // Show 3 — draft, just created today
        $show3 = WhatnotShow::firstOrCreate(
            ['title' => 'Mojo Break #43 — TCG Night'],
            [
                'whatnot_channel_id' => $channel?->id,
                'show_date'          => Carbon::now()->toDateString(),
                'status'             => 'draft',
                'source'             => 'manual',
                'created_by'         => 1,
            ]
        );
        $show3->streamers()->syncWithoutDetaching([$taylor->id => ['is_primary' => true]]);

        ShowFinancial::firstOrCreate(['whatnot_show_id' => $show3->id]);

        ShowSale::firstOrCreate(
            ['whatnot_show_id' => $show3->id, 'order_id' => 'WN-10020'],
            [
                'inventory_item_id' => $pokemon->id,
                'item_name'         => 'Pokémon SV Booster Pack',
                'sku'               => 'PKM-2024-005',
                'quantity'          => 10,
                'sale_price'        => 6.00,
                'buyer_username'    => 'pkmnmaster',
                'sale_type'         => 'fixed_price',
            ]
        );
        ShowSale::firstOrCreate(
            ['whatnot_show_id' => $show3->id, 'order_id' => 'WN-10021'],
            [
                'inventory_item_id' => $mtg->id,
                'item_name'         => 'MTG Bloomburrow Set Booster Box',
                'sku'               => 'MTG-2024-006',
                'quantity'          => 2,
                'sale_price'        => 145.00,
                'buyer_username'    => 'mtgaddict',
                'sale_type'         => 'break_slot',
            ]
        );

        // ── Payouts ───────────────────────────────────────────────────────────

        // Week 1 batch — finalized and paid
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
            ['whatnot_show_id' => $show1->id, 'streamer_id' => $jordan->id],
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

        // Week 2 batch — draft (show 2 payouts pending)
        $batch2 = WeeklyPayoutBatch::firstOrCreate(
            ['week_start' => Carbon::now()->subDays(5)->startOfWeek()->toDateString()],
            [
                'week_end'   => Carbon::now()->subDays(5)->endOfWeek()->toDateString(),
                'status'     => 'draft',
                'total_payout' => 0,
                'created_by' => 1,
            ]
        );

        Payout::firstOrCreate(
            ['whatnot_show_id' => $show2->id, 'streamer_id' => $jordan->id],
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
            ['whatnot_show_id' => $show2->id, 'streamer_id' => $taylor->id],
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
    }
}
