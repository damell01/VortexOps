<?php

namespace Database\Seeders;

use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\Streamer;
use App\Models\WhatnotChannel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        // Channels
        WhatnotChannel::firstOrCreate(['name' => 'Vortex Channel 2'], ['status' => 'active', 'whatnot_username' => 'vortexbreaks2']);
        WhatnotChannel::firstOrCreate(['name' => 'Vortex Channel 3'], ['status' => 'active', 'whatnot_username' => 'vortexbreaks3']);

        // Streamers
        $jordan = Streamer::firstOrCreate(['name' => 'Jordan'], [
            'email' => 'jordan@vortexbreaks.com',
            'payout_type' => 'profit_share',
            'payout_percentage' => 35.00,
            'include_tips' => true,
            'status' => 'active',
            'adp_employee_id' => 'ADP-001',
        ]);
        $taylor = Streamer::firstOrCreate(['name' => 'Taylor'], [
            'email' => 'taylor@vortexbreaks.com',
            'payout_type' => 'package',
            'package_rate' => 15.00,
            'include_tips' => true,
            'status' => 'active',
            'adp_employee_id' => 'ADP-002',
        ]);
        $morgan = Streamer::firstOrCreate(['name' => 'Morgan'], [
            'email' => 'morgan@vortexbreaks.com',
            'payout_type' => 'hourly',
            'hourly_rate' => 22.50,
            'include_tips' => false,
            'status' => 'on_leave',
        ]);

        // Streamer inventory locations
        $jordanLoc = InventoryLocation::firstOrCreate(['name' => 'Jordan Inventory'], [
            'type' => 'streamer_inventory',
            'streamer_id' => $jordan->id,
            'status' => 'active',
        ]);
        $taylorLoc = InventoryLocation::firstOrCreate(['name' => 'Taylor Inventory'], [
            'type' => 'streamer_inventory',
            'streamer_id' => $taylor->id,
            'status' => 'active',
        ]);

        $mainStorage   = InventoryLocation::where('name', 'Main Storage')->first();
        $returnedLoc   = InventoryLocation::where('name', 'Returned Inventory')->first();
        $damagedLoc    = InventoryLocation::where('name', 'Damaged Inventory')->first();
        $fulfillment   = InventoryLocation::where('name', 'Fulfillment Area')->first();

        // Inventory items
        $items = [
            ['sku' => 'BCH-2024-001', 'name' => '2024 Bowman Chrome Hobby Box',      'category' => 'Baseball',      'unit_cost' => 125.00, 'reorder_level' => 5],
            ['sku' => 'TPS-2024-002', 'name' => '2024 Topps Series 1 Hobby Box',      'category' => 'Baseball',      'unit_cost' => 95.00,  'reorder_level' => 8],
            ['sku' => 'PRI-2024-003', 'name' => '2024 Prizm Basketball Hobby Box',    'category' => 'Basketball',    'unit_cost' => 185.00, 'reorder_level' => 3],
            ['sku' => 'OPT-2024-004', 'name' => '2024 Donruss Optic Football Box',   'category' => 'Football',      'unit_cost' => 145.00, 'reorder_level' => 4],
            ['sku' => 'PKM-2024-005', 'name' => 'Pokémon Scarlet & Violet Booster',  'category' => 'TCG',           'unit_cost' => 4.50,   'reorder_level' => 50],
            ['sku' => 'MTG-2024-006', 'name' => 'MTG Bloomburrow Set Booster Box',   'category' => 'TCG',           'unit_cost' => 110.00, 'reorder_level' => 6],
            ['sku' => 'SCR-2025-007', 'name' => '2025 Bowman Draft HTA Box',          'category' => 'Baseball',      'unit_cost' => 210.00, 'reorder_level' => 2],
            ['sku' => 'NBA-2024-008', 'name' => '2024 Hoops Basketball Blaster',      'category' => 'Basketball',    'unit_cost' => 22.00,  'reorder_level' => 20],
        ];

        $createdItems = [];
        foreach ($items as $item) {
            $createdItems[] = InventoryItem::firstOrCreate(['sku' => $item['sku']], array_merge($item, ['is_active' => true]));
        }

        // Stock entries
        $stockData = [
            [$createdItems[0], $mainStorage,   12],
            [$createdItems[0], $jordanLoc,      6],
            [$createdItems[1], $mainStorage,   24],
            [$createdItems[1], $taylorLoc,      3],  // low stock
            [$createdItems[2], $mainStorage,    8],
            [$createdItems[2], $jordanLoc,      2],  // low stock
            [$createdItems[3], $mainStorage,   15],
            [$createdItems[3], $taylorLoc,      1],  // low stock
            [$createdItems[4], $mainStorage,  120],
            [$createdItems[4], $fulfillment,   30],
            [$createdItems[5], $mainStorage,    7],
            [$createdItems[6], $mainStorage,    1],  // low stock
            [$createdItems[7], $mainStorage,   45],
            [$createdItems[7], $returnedLoc,    3],
        ];

        foreach ($stockData as [$item, $loc, $qty]) {
            if (!$loc) continue;
            InventoryStock::updateOrCreate(
                ['inventory_item_id' => $item->id, 'inventory_location_id' => $loc->id],
                ['quantity' => $qty]
            );
        }

        // Movement history
        $movements = [
            [$createdItems[0], null,         $mainStorage, 12,  'opening',       'Initial stock received'],
            [$createdItems[0], $mainStorage, $jordanLoc,    6,  'transfer',      'Transferred to Jordan for stream'],
            [$createdItems[1], null,         $mainStorage, 27,  'opening',       'Initial stock received'],
            [$createdItems[1], $mainStorage, $taylorLoc,    3,  'transfer',      'Transferred to Taylor'],
            [$createdItems[2], null,         $mainStorage,  8,  'opening',       'Opening inventory'],
            [$createdItems[2], $mainStorage, $jordanLoc,    2,  'transfer',      'Jordan stream prep'],
            [$createdItems[4], null,         $mainStorage, 150, 'opening',       'Bulk Pokemon restock'],
            [$createdItems[4], $mainStorage, $fulfillment,  30, 'transfer',      'Moved to fulfillment'],
            [$createdItems[7], $mainStorage, $returnedLoc,   3, 'return',        'Customer returns processed'],
            [$createdItems[3], $mainStorage, $damagedLoc,    0, 'adjustment',    'Count correction after audit'],
        ];

        foreach ($movements as [$item, $from, $to, $qty, $type, $reason]) {
            if (!$to) continue;
            InventoryMovement::create([
                'inventory_item_id'  => $item->id,
                'from_location_id'   => $from?->id,
                'to_location_id'     => $to->id,
                'quantity'           => $qty,
                'movement_type'      => $type,
                'reason'             => $reason,
                'created_by'         => 1,
            ]);
        }
    }
}
