<?php

namespace Database\Seeders;

use App\Models\DeductionRequest;
use App\Models\Pallet;
use App\Models\PalletLine;
use App\Models\User;
use App\Models\Vendor;
use App\Services\ReceivingService;
use App\Models\DeductionRequestLine;
use App\Models\FeedbackTicket;
use App\Models\InventoryCase;
use App\Models\InventoryItem;
use App\Models\InventoryItemContent;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\InventoryValueSnapshot;
use App\Models\Payout;
use App\Models\ReceivingSession;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\StreamerLoan;
use App\Models\StreamerLogEntry;
use App\Models\WeeklyPayoutBatch;
use App\Models\WhatnotChannel;
use App\Models\WhatnotShowOrder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        // Demo data builds on the base locations, channel, and roles that
        // DefaultDataSeeder creates. Ensure they exist first so this seeder is
        // self-sufficient — safe to run from the Demo Data button on a bare
        // deployment, not just as part of a full db:seed. Both are idempotent.
        $this->call(DefaultDataSeeder::class);

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

        // Locations carry the channel, and everything channel-scoped —
        // including the per-channel valuation snapshots and the value-vs-revenue
        // widget — joins through them. Left unset, picking a channel showed
        // real revenue against $0 of inventory.
        $demoChannel = WhatnotChannel::where('name', 'Vortex Main Channel')->first();

        if ($demoChannel) {
            foreach ([$mainStorage, $returnedLoc, $damagedLoc, $fulfillment, $jordanLoc, $taylorLoc, $alexLoc] as $loc) {
                if ($loc && $loc->whatnot_channel_id === null) {
                    $loc->update(['whatnot_channel_id' => $demoChannel->id]);
                }
            }
        }

        // ── Inventory items ──────────────────────────────────────────────────
        // average_cost is the weighted average the whole app values stock at —
        // Analytics, Inventory Age, the value KPIs and the snapshot command all
        // read it, and treat a null as zero. unit_cost is only the last price
        // paid, so seeding that alone left every figure reading $0. The two
        // deliberately differ here: WAC drifts as restocks come in at new prices.
        $itemData = [
            ['sku' => 'BCH-2024-001', 'name' => '2024 Bowman Chrome Hobby Box',    'category' => 'Baseball',   'unit_cost' => 125.00, 'average_cost' => 121.40, 'reorder_level' => 5],
            ['sku' => 'TPS-2024-002', 'name' => '2024 Topps Series 1 Hobby Box',   'category' => 'Baseball',   'unit_cost' => 95.00,  'average_cost' => 97.25,  'reorder_level' => 8],
            ['sku' => 'PRI-2024-003', 'name' => '2024 Prizm Basketball Hobby Box', 'category' => 'Basketball', 'unit_cost' => 185.00, 'average_cost' => 178.90, 'reorder_level' => 3],
            ['sku' => 'OPT-2024-004', 'name' => '2024 Donruss Optic Football Box', 'category' => 'Football',   'unit_cost' => 145.00, 'average_cost' => 145.00, 'reorder_level' => 4],
            ['sku' => 'PKM-2024-005', 'name' => 'Pokémon SV Booster Pack',         'category' => 'TCG',        'unit_cost' => 4.50,   'average_cost' => 4.28,   'reorder_level' => 50],
            ['sku' => 'MTG-2024-006', 'name' => 'MTG Bloomburrow Set Booster Box', 'category' => 'TCG',        'unit_cost' => 110.00, 'average_cost' => 112.75, 'reorder_level' => 6],
            ['sku' => 'SCR-2025-007', 'name' => '2025 Bowman Draft HTA Box',       'category' => 'Baseball',   'unit_cost' => 210.00, 'average_cost' => 203.50, 'reorder_level' => 2],
            ['sku' => 'NBA-2024-008', 'name' => '2024 Hoops Basketball Blaster',   'category' => 'Basketball', 'unit_cost' => 22.00,  'average_cost' => 21.15,  'reorder_level' => 20],
            ['sku' => 'NFL-2025-009', 'name' => '2025 Select Football Hobby Box',  'category' => 'Football',   'unit_cost' => 165.00, 'average_cost' => 168.40, 'reorder_level' => 3],
            ['sku' => 'YGO-2024-010', 'name' => 'Yu-Gi-Oh! Phantom Nightmare Box', 'category' => 'TCG',        'unit_cost' => 65.00,  'average_cost' => 63.80,  'reorder_level' => 8],
        ];

        $items = [];
        foreach ($itemData as $d) {
            $items[] = $this->upsertItem($d);
        }
        [$bowman, $topps, $prizm, $optic, $pokemon, $mtg, $bowmanDraft, $hoops, $select, $yugioh] = $items;

        // ── Containers ───────────────────────────────────────────────────────
        // A case on the shelf is one unit; the catalogue still needs to say what
        // is inside it and what each of those is worth. These give the Contents
        // view something to open, and the per-unit maths a case to run on.
        $containerData = [
            [
                'sku' => 'BCH-2024-C01', 'name' => '2024 Bowman Chrome Hobby CASE',
                'category' => 'Baseball', 'unit_cost' => 1440.00, 'average_cost' => 1428.00,
                'reorder_level' => 1, 'holds' => [[$bowman, 12, 'case']],
            ],
            [
                'sku' => 'PRI-2024-C02', 'name' => '2024 Prizm Basketball Hobby CASE',
                'category' => 'Basketball', 'unit_cost' => 2160.00, 'average_cost' => 2124.00,
                'reorder_level' => 1, 'holds' => [[$prizm, 12, 'case']],
            ],
            [
                'sku' => 'PKM-2024-C03', 'name' => 'Pokémon SV Booster Box (36 packs)',
                'category' => 'TCG', 'unit_cost' => 149.00, 'average_cost' => 145.60,
                'reorder_level' => 4, 'holds' => [[$pokemon, 36, 'box']],
            ],
            [
                'sku' => 'MIX-2025-C04', 'name' => 'Football Mixer Case (Optic + Select)',
                'category' => 'Football', 'unit_cost' => 1780.00, 'average_cost' => 1755.00,
                'reorder_level' => 1, 'holds' => [[$optic, 8, 'case'], [$select, 4, 'case']],
            ],
        ];

        $containers = [];
        foreach ($containerData as $d) {
            $holds = $d['holds'];
            unset($d['holds']);

            $container = $this->upsertItem($d + ['is_container' => true]);
            $containers[] = $container;

            foreach ($holds as [$child, $qty, $unitType]) {
                // Soft deletes plus the parent/child unique index mean a
                // previously removed line still occupies the slot: a plain
                // firstOrCreate misses it, then collides on insert.
                $line = InventoryItemContent::withTrashed()->firstOrNew([
                    'parent_inventory_item_id' => $container->id,
                    'child_inventory_item_id'  => $child->id,
                ]);
                $line->quantity_per_parent = $qty;
                $line->unit_type           = $unitType;
                $line->deleted_at          = null;
                $line->save();
            }
        }
        [$bowmanCase, $prizmCase, $pokemonBox, $mixerCase] = $containers;

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
            [$bowmanCase,  $mainStorage,  3],
            [$prizmCase,   $mainStorage,  2],
            [$pokemonBox,  $mainStorage, 14], [$pokemonBox,  $fulfillment, 6],
            [$mixerCase,   $mainStorage,  1],
        ];

        foreach ($stockData as [$item, $loc, $qty]) {
            if (! $loc) continue;
            InventoryStock::updateOrCreate(
                ['inventory_item_id' => $item->id, 'inventory_location_id' => $loc->id],
                ['quantity' => $qty]
            );
        }

        // ── Movement history ─────────────────────────────────────────────────
        // The trailing number is how many days ago the movement happened.
        // Inventory Age reads the newest receipt (into a location, from
        // nowhere) as the arrival date, so leaving these all at "now" put every
        // item in the 0–30 day bucket and the page read as a single bar.
        $movements = [
            [$bowman,     null,        $mainStorage, 12, 'opening',  'Initial stock received',            22],
            [$bowman,     $mainStorage, $jordanLoc,   4, 'transfer', 'Transferred to Jordan for stream',  18],
            [$topps,      null,        $mainStorage, 25, 'opening',  'Initial stock received',            47],
            [$topps,      $mainStorage, $taylorLoc,   3, 'transfer', 'Transferred to Taylor',             41],
            [$prizm,      null,        $mainStorage,  7, 'opening',  'Opening inventory',                 74],
            [$prizm,      $mainStorage, $jordanLoc,   2, 'transfer', 'Jordan stream prep',                70],
            [$pokemon,    null,        $mainStorage, 140,'opening',  'Bulk Pokémon restock',               9],
            [$pokemon,    $mainStorage, $fulfillment, 30, 'transfer','Moved to fulfillment',               6],
            [$hoops,      $mainStorage, $returnedLoc,  3, 'return',  'Customer returns processed',         4],
            [$select,     null,        $mainStorage,  8, 'opening',  'Select Football initial stock',     58],
            [$select,     $mainStorage, $alexLoc,      2, 'transfer', 'Alex stream prep',                 52],
            [$yugioh,     null,        $mainStorage,  23, 'opening', 'Yu-Gi-Oh bulk restock',            133],
            [$yugioh,     $mainStorage, $fulfillment,  5, 'transfer','Moved to fulfillment',             128],
            [$optic,      null,        $mainStorage,  14, 'opening', 'Optic Football initial stock',      96],
            [$mtg,        null,        $mainStorage,   7, 'opening', 'Bloomburrow allocation',            35],
            [$bowmanDraft,null,        $mainStorage,   1, 'opening', 'Single HTA box held back',         181],
            [$bowmanCase, null,        $mainStorage,   3, 'opening', 'Bowman Chrome case delivery',       16],
            [$prizmCase,  null,        $mainStorage,   2, 'opening', 'Prizm case delivery',               63],
            [$pokemonBox, null,        $mainStorage,  20, 'opening', 'Pokémon booster box delivery',      11],
            [$mixerCase,  null,        $mainStorage,   1, 'opening', 'Football mixer case delivery',     108],
        ];

        foreach ($movements as [$item, $from, $to, $qty, $type, $reason, $daysAgo]) {
            if (! $to) continue;

            // Guarded per movement rather than by one probe at the top of the
            // block. A single global check goes stale the moment a row is added
            // to this list — it reads as "already seeded" and silently skips
            // every new movement, which is how the containers ended up with no
            // receipt history and the age buckets stayed flat.
            $exists = InventoryMovement::where('inventory_item_id', $item->id)
                ->where('to_location_id', $to->id)
                ->where('reason', $reason)
                ->when($from, fn ($q) => $q->where('from_location_id', $from->id))
                ->when(! $from, fn ($q) => $q->whereNull('from_location_id'))
                ->exists();

            if ($exists) {
                continue;
            }

            $movement = new InventoryMovement([
                'inventory_item_id' => $item->id,
                'from_location_id'  => $from?->id,
                'to_location_id'    => $to->id,
                'quantity'          => $qty,
                'unit_cost'         => $item->average_cost,
                'movement_type'     => $type,
                'reason'            => $reason,
                'created_by'        => 1,
            ]);

            // created_at isn't fillable, so it has to be set on the instance.
            // Eloquent leaves an already-dirty timestamp alone on save.
            $movement->created_at = $movement->updated_at = Carbon::now()->subDays($daysAgo);
            $movement->save();
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

        // ── Streamer login accounts (test data scoping as a streamer) ────────
        // Each demo streamer gets a User with the "streamer" role, linked via
        // streamers.user_id, so you can log in as them (password: demopassword)
        // and see only their own shows, payouts, and log entries.
        $streamerRole = Role::firstOrCreate(['name' => 'streamer', 'guard_name' => 'web']);
        foreach ([$jordan, $taylor, $morgan, $alex] as $s) {
            if (! $s->email) {
                continue;
            }
            $u = User::firstOrCreate(
                ['email' => $s->email],
                ['name' => $s->name, 'password' => Hash::make('demopassword'), 'email_verified_at' => now()],
            );
            $u->syncRoles([$streamerRole]);
            if ($s->user_id !== $u->id) {
                $s->update(['user_id' => $u->id]);
            }
        }

        // ── Fulfillment login account (test data scoping as fulfillment) ─────
        // Assigned to show1 only, so logging in as them (password: demopassword)
        // demonstrates the Fulfillment Center scoped to just their assigned show.
        $fulfillmentRole = Role::firstOrCreate(['name' => 'fulfillment', 'guard_name' => 'web']);
        $fulfillmentUser = User::firstOrCreate(
            ['email' => 'fulfillment@vortexbreaks.com'],
            ['name' => 'Fulfillment Demo', 'password' => Hash::make('demopassword'), 'email_verified_at' => now()],
        );
        $fulfillmentUser->syncRoles([$fulfillmentRole]);
        $show1->fulfillmentUsers()->syncWithoutDetaching([$fulfillmentUser->id]);

        // ── Fulfillment admin login account (sees every channel's fulfillment
        // work and can use the channel switcher, unlike the scoped fulfillment
        // account above) ───────────────────────────────────────────────────────
        $fulfillmentAdminRole = Role::firstOrCreate(['name' => 'fulfillment_admin', 'guard_name' => 'web']);
        $fulfillmentAdminUser = User::firstOrCreate(
            ['email' => 'fulfillment-admin@vortexbreaks.com'],
            ['name' => 'Fulfillment Admin Demo', 'password' => Hash::make('demopassword'), 'email_verified_at' => now()],
        );
        $fulfillmentAdminUser->syncRoles([$fulfillmentAdminRole]);

        // ── Per-show orders (items sold) ─────────────────────────────────────
        // Drives the Streamer Log items editor, Product Insights (revenue,
        // sell-through), and per-show P&L. Some are pre-mapped to inventory,
        // some left unmapped so there's mapping work to demo.
        $makeOrder = function (Show $show, string $oid, array $attrs): void {
            WhatnotShowOrder::firstOrCreate(
                ['show_id' => $show->id, 'whatnot_order_id' => $oid],
                array_merge(['status' => 'completed', 'show_date' => $show->show_date, 'quantity' => 1], $attrs),
            );
        };
        $mapped = fn (InventoryItem $item, ?InventoryLocation $loc, float $cost, int $qty): array => [
            'inventory_item_id'     => $item->id,
            'inventory_location_id' => $loc?->id,
            'unit_cost'             => $cost,
            'total_cost'            => round($cost * $qty, 2),
        ];

        // Show 1 (reconciled) — fully mapped
        $makeOrder($show1, 'WN-S1-001', ['buyer_username' => 'cardking22', 'item_name' => '2024 Bowman Chrome Hobby Box', 'quantity' => 2, 'total_price' => 320.00] + $mapped($bowman, $jordanLoc, 125.00, 2));
        $makeOrder($show1, 'WN-S1-002', ['buyer_username' => 'rookiehunter', 'item_name' => '2024 Topps Series 1', 'quantity' => 2, 'total_price' => 240.00] + $mapped($topps, $taylorLoc, 95.00, 2));
        $makeOrder($show1, 'WN-S1-003', ['buyer_username' => 'pccollector', 'item_name' => 'Pokémon SV Booster', 'quantity' => 4, 'total_price' => 44.00] + $mapped($pokemon, $mainStorage, 4.50, 4));

        // Show 2 (pending_approval) — one mapped, one left to map
        $makeOrder($show2, 'WN-S2-001', ['buyer_username' => 'hoopsfan', 'item_name' => '2024 Prizm Basketball', 'quantity' => 3, 'total_price' => 640.00] + $mapped($prizm, $jordanLoc, 185.00, 3));
        $makeOrder($show2, 'WN-S2-002', ['buyer_username' => 'gridiron', 'item_name' => 'Optic Football Box', 'quantity' => 2, 'total_price' => 300.00]);

        // Show 3 (pending_review, TCG) — unmapped, streamer's job to map
        $makeOrder($show3, 'WN-S3-001', ['buyer_username' => 'tcgmaster', 'item_name' => 'MTG Bloomburrow Box', 'quantity' => 1, 'total_price' => 140.00]);
        $makeOrder($show3, 'WN-S3-002', ['buyer_username' => 'yugiking', 'item_name' => 'Yu-Gi-Oh Phantom Nightmare', 'quantity' => 1, 'total_price' => 90.00]);
        $makeOrder($show3, 'WN-S3-003', ['buyer_username' => 'duelist', 'item_name' => 'Pokémon SV Booster', 'quantity' => 6, 'total_price' => 72.00]);

        // Show 4 — a mapped sale
        $makeOrder($show4, 'WN-S4-001', ['buyer_username' => 'nflcards', 'item_name' => '2025 Select Football', 'quantity' => 1, 'total_price' => 195.00] + $mapped($select, $alexLoc, 165.00, 1));

        // ── Streamer log entries (the review → approval workflow) ─────────────
        // One entry per show, spread across the pipeline so every state is
        // demoable: approved+locked, awaiting-admin, and streamer-to-do.
        $logStates = [
            [$show1, 'admin_approved', $jordan],    // locked — test view-only + send-back
            [$show2, 'streamer_reviewed', $jordan], // awaiting admin approval
            [$show3, 'pending', $taylor],           // streamer still to fill
            [$show4, 'pending', $alex],
        ];
        foreach ($logStates as [$show, $status, $streamer]) {
            StreamerLogEntry::firstOrCreate(
                ['show_id' => $show->id],
                [
                    'streamer_id'    => $streamer->id,
                    'status'         => $status,
                    'gross_revenue'  => $show->gross_revenue,
                    'hours_streamed' => $show->show_duration ? round($show->show_duration / 60, 1) : null,
                    'reviewed_at'    => $status === 'admin_approved' ? Carbon::now()->subDays(10) : null,
                    'reviewed_by'    => $status === 'admin_approved' ? 1 : null,
                ],
            );
        }

        // ── Scannable pallet (barcode receiving demo) ────────────────────────
        // A pallet mid-receipt whose cases carry real barcodes, so you can open
        // Receive Pallet and scan them (VX-CASE-0001 … VX-CASE-0004).
        $scanPallet = Pallet::firstOrCreate(
            ['reference' => 'PO-2026-003'],
            [
                'vendor_id'  => $vendor->id,
                'status'     => 'receiving',
                'notes'      => 'Demo — scan the case barcodes (VX-CASE-0001 …) on the Receive page.',
                'created_by' => 1,
            ],
        );
        if ($scanPallet->wasRecentlyCreated) {
            $scanLine = PalletLine::create([
                'pallet_id'             => $scanPallet->id,
                'line_number'           => 1,
                'description'           => '2025 Bowman Draft HTA Box',
                'inventory_item_id'     => $bowmanDraft->id,
                'inventory_location_id' => $mainStorage?->id,
                'case_count'            => 4,
                'quantity_per_case'     => 6,
                'unit_cost'             => 210.00,
            ]);
            for ($i = 1; $i <= 4; $i++) {
                InventoryCase::create([
                    'pallet_line_id' => $scanLine->id,
                    'barcode'        => sprintf('VX-CASE-%04d', $i),
                    'status'         => 'expected',
                ]);
            }
        }

        // ── Streamer loan (payout repayment deduction) ───────────────────────
        StreamerLoan::firstOrCreate(
            ['streamer_id' => $alex->id, 'label' => 'Equipment advance'],
            [
                'original_amount'    => 600.00,
                'weekly_repayment'   => 50.00,
                'remaining_balance'  => 450.00,
                'deduct_from_payout' => true,
                'status'             => 'active',
                'notes'              => 'Demo loan — repayments deduct from weekly payouts.',
            ],
        );

        // ── Receiving session (AI document-matching demo) ────────────────────
        // An empty session ready for a manifest. Open it, hit "Import lines
        // manually", and upload the Sample manifest (or paste lines) to watch the
        // matching pipeline sort them into auto-matched / needs-review / new.
        ReceivingSession::firstOrCreate(
            ['invoice_number' => 'INV-DEMO-001'],
            [
                'vendor_id'      => $vendor->id,
                'received_by'    => 1,
                'purchase_order' => 'PO-DEMO-001',
                'status'         => 'pending',
                'notes'          => 'Demo — open this, choose "Import lines manually", and upload the Sample manifest to watch AI matching sort each line.',
            ],
        );

        // ── Feedback tickets (the in-app feedback flow) ──────────────────────
        FeedbackTicket::firstOrCreate(
            ['title' => 'Add CSV export to Product Insights'],
            ['description' => 'Would love to export the dead-stock list to CSV.', 'type' => 'suggestion', 'status' => 'open', 'priority' => 'medium', 'submitted_name' => 'Jordan'],
        );
        FeedbackTicket::firstOrCreate(
            ['title' => 'Confirm tips are in the payout preview'],
            ['description' => 'Double-check tips are included in the weekly preview total.', 'type' => 'bug', 'status' => 'in_progress', 'priority' => 'high', 'submitted_name' => 'Taylor'],
        );

        // Last, so the valuation it anchors on includes the stock the receiving
        // demo above credits — not just what the Stock block set directly.
        $this->seedValueSnapshots();
    }

    /**
     * Create a demo item, or bring an existing one up to date on the fields
     * that are safe to correct.
     *
     * Plain firstOrCreate would leave installs that were seeded before
     * average_cost was added still valuing their stock at zero, but a blanket
     * updateOrCreate would overwrite names and costs someone had edited by
     * hand. So identity and descriptive fields are create-only, and the
     * valuation fields are backfilled only while they are still unset.
     */
    private function upsertItem(array $data): InventoryItem
    {
        $item = InventoryItem::firstOrCreate(
            ['sku' => $data['sku']],
            array_merge($data, ['is_active' => true]),
        );

        if ((float) ($item->average_cost ?? 0) <= 0 && isset($data['average_cost'])) {
            $item->average_cost = $data['average_cost'];
        }

        // Marking a container is what makes the Contents view appear at all, so
        // an item seeded before containers existed needs it applied too.
        if (! empty($data['is_container']) && ! $item->is_container) {
            $item->is_container = true;
        }

        if ($item->isDirty()) {
            $item->save();
        }

        return $item;
    }

    /**
     * Backfill the daily inventory valuation history.
     *
     * Analytics prefers these snapshots and only falls back to unwinding
     * movement history when there are fewer than two — so without them the
     * trend chart never exercises the path production actually uses. The
     * command that writes them (inventory:snapshot-value) runs once a day and
     * cannot see backwards, hence seeding the history here.
     */
    private function seedValueSnapshots(): void
    {
        // Long enough to fill the widest consumer: the value-vs-revenue widget
        // charts six calendar months, and a month with no snapshots averages to
        // $0 — which plots real revenue against nothing. Analytics' own 30-day
        // window is comfortably inside this.
        $days  = 210;
        $since = Carbon::today()->subDays($days)->toDateString();

        // Today's figures come from the same query the scheduled command uses,
        // so the seeded history lands on the real current value rather than
        // drifting away from what the KPI tiles report.
        $channelIds = WhatnotChannel::pluck('id')->all();

        foreach (array_merge([null], $channelIds) as $channelId) {
            // Checked per series, not across the table: a combined history that
            // was already seeded would otherwise satisfy a global guard and
            // leave every channel series empty.
            $existing = InventoryValueSnapshot::where('snapshot_date', '>=', $since)
                ->where('whatnot_channel_id', $channelId)
                ->count();

            if ($existing >= $days) {
                continue;
            }

            $today = $this->currentValuation($channelId);

            if ($today['total_value'] <= 0) {
                continue;
            }

            for ($offset = $days; $offset >= 0; $offset--) {
                // Deterministic so re-seeding redraws the same chart: a gentle
                // climb toward today with a weekly restock/sell-down wobble.
                $progress = 1 - ($offset / $days);
                $factor   = (0.78 + (0.22 * $progress)) * (1 + (0.035 * sin($offset / 4.5)));

                $date = Carbon::today()->subDays($offset);

                InventoryValueSnapshot::updateOrCreate(
                    ['snapshot_date' => $date->toDateString(), 'whatnot_channel_id' => $channelId],
                    [
                        'total_value'    => round($today['total_value'] * $factor, 2),
                        'total_quantity' => round($today['total_quantity'] * $factor, 2),
                        'total_items'    => $today['total_items'],
                    ],
                );
            }
        }
    }

    /**
     * Today's stock valuation at weighted average cost, scoped to a channel or
     * combined across all of them when null. Mirrors SnapshotInventoryValue.
     *
     * @return array{total_value: float, total_quantity: float, total_items: int}
     */
    private function currentValuation(?int $channelId): array
    {
        $query = DB::table('inventory_stock')
            ->join('products', 'inventory_stock.inventory_item_id', '=', 'products.id')
            ->where('products.average_cost', '>', 0);

        if ($channelId) {
            $query->join('inventory_locations', 'inventory_locations.id', '=', 'inventory_stock.inventory_location_id')
                ->where('inventory_locations.whatnot_channel_id', $channelId);
        }

        $totals = $query->selectRaw(
            'SUM(inventory_stock.quantity * products.average_cost) as total_value, ' .
            'SUM(inventory_stock.quantity) as total_quantity, ' .
            'COUNT(DISTINCT products.id) as total_items'
        )->first();

        return [
            'total_value'    => (float) ($totals->total_value ?? 0),
            'total_quantity' => (float) ($totals->total_quantity ?? 0),
            'total_items'    => (int) ($totals->total_items ?? 0),
        ];
    }
}
