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

        $demoChannel = WhatnotChannel::where('name', 'Vortex Main Channel')->first();

        if ($demoChannel) {
            foreach ([$mainStorage, $returnedLoc, $damagedLoc, $fulfillment, $jordanLoc, $taylorLoc, $alexLoc] as $loc) {
                if ($loc && $loc->whatnot_channel_id === null) {
                    $loc->update(['whatnot_channel_id' => $demoChannel->id]);
                }
            }
        }

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

        $containerData = [
            ['sku' => 'BCH-2024-C01', 'name' => '2024 Bowman Chrome Hobby CASE', 'category' => 'Baseball', 'unit_cost' => 1440.00, 'average_cost' => 1428.00, 'reorder_level' => 1, 'holds' => [[$bowman, 12, 'case']]],
            ['sku' => 'PRI-2024-C02', 'name' => '2024 Prizm Basketball Hobby CASE', 'category' => 'Basketball', 'unit_cost' => 2160.00, 'average_cost' => 2124.00, 'reorder_level' => 1, 'holds' => [[$prizm, 12, 'case']]],
            ['sku' => 'PKM-2024-C03', 'name' => 'Pokémon SV Booster Box (36 packs)', 'category' => 'TCG', 'unit_cost' => 149.00, 'average_cost' => 145.60, 'reorder_level' => 4, 'holds' => [[$pokemon, 36, 'box']]],
            ['sku' => 'MIX-2025-C04', 'name' => 'Football Mixer Case (Optic + Select)', 'category' => 'Football', 'unit_cost' => 1780.00, 'average_cost' => 1755.00, 'reorder_level' => 1, 'holds' => [[$optic, 8, 'case'], [$select, 4, 'case']]],
        ];

        $containers = [];
        foreach ($containerData as $d) {
            $holds = $d['holds'];
            unset($d['holds']);
            $container = $this->upsertItem($d + ['is_container' => true]);
            $containers[] = $container;
            foreach ($holds as [$child, $qty, $unitType]) {
                $line = InventoryItemContent::withTrashed()->firstOrNew([
                    'parent_inventory_item_id' => $container->id,
                    'child_inventory_item_id'  => $child->id,
                ]);
                $line->quantity_per_parent = $qty;
                $line->unit_type = $unitType;
                $line->deleted_at = null;
                $line->save();
            }
        }
        [$bowmanCase, $prizmCase, $pokemonBox, $mixerCase] = $containers;

        $stockData = [
            [$bowman,$mainStorage,8],[$bowman,$jordanLoc,4],[$topps,$mainStorage,22],[$topps,$taylorLoc,3],[$prizm,$mainStorage,5],[$prizm,$jordanLoc,2],[$optic,$mainStorage,13],[$optic,$taylorLoc,1],[$pokemon,$mainStorage,110],[$pokemon,$fulfillment,30],[$mtg,$mainStorage,7],[$bowmanDraft,$mainStorage,1],[$hoops,$mainStorage,41],[$hoops,$returnedLoc,3],[$select,$mainStorage,6],[$select,$alexLoc,2],[$yugioh,$mainStorage,18],[$yugioh,$fulfillment,5],[$bowmanCase,$mainStorage,3],[$prizmCase,$mainStorage,2],[$pokemonBox,$mainStorage,14],[$pokemonBox,$fulfillment,6],[$mixerCase,$mainStorage,1],
        ];
        foreach ($stockData as [$item,$loc,$qty]) {
            if ($loc) InventoryStock::updateOrCreate(['inventory_item_id'=>$item->id,'inventory_location_id'=>$loc->id],['quantity'=>$qty]);
        }

        $channel = $demoChannel;

        $show1 = Show::firstOrCreate(['title' => 'Mojo Break #41 — Baseball Night'], [
            'whatnot_channel_id'=>$channel?->id,'show_date'=>Carbon::now()->subDays(14)->toDateString(),'show_duration'=>210,'units_sold'=>10,'gross_revenue'=>1240.00,'whatnot_net'=>1140.80,'tips'=>42.00,'import_source'=>'manual','status'=>'reconciled','created_by'=>1,
        ]);
        $show1->streamers()->syncWithoutDetaching([$jordan->id => ['is_primary' => true]]);
        $req1 = DeductionRequest::firstOrCreate(['show_id'=>$show1->id,'streamer_id'=>$jordan->id],['status'=>'processed','approved_by'=>1,'approved_at'=>Carbon::now()->subDays(13),'processed_by'=>1,'processed_at'=>Carbon::now()->subDays(13)]);
        DeductionRequestLine::firstOrCreate(['deduction_request_id'=>$req1->id,'inventory_item_id'=>$bowman->id],['inventory_location_id'=>$jordanLoc->id,'quantity_suggested'=>4,'quantity_approved'=>4,'unit_cost_snapshot'=>125.00,'line_total'=>500.00,'raw_description'=>'4x 2024 Bowman Chrome Hobby Box','ai_confidence'=>'high']);
        DeductionRequestLine::firstOrCreate(['deduction_request_id'=>$req1->id,'inventory_item_id'=>$topps->id],['inventory_location_id'=>$taylorLoc->id,'quantity_suggested'=>2,'quantity_approved'=>2,'unit_cost_snapshot'=>95.00,'line_total'=>190.00,'raw_description'=>'2x 2024 Topps Series 1','ai_confidence'=>'high']);

        $show2 = Show::firstOrCreate(['title'=>'Mojo Break #42 — Hoops & Football'],['whatnot_channel_id'=>$channel?->id,'show_date'=>Carbon::now()->subDays(3)->toDateString(),'show_duration'=>180,'units_sold'=>9,'gross_revenue'=>960.00,'whatnot_net'=>883.20,'tips'=>28.00,'import_source'=>'manual','status'=>'pending_approval','created_by'=>1]);
        $show2->streamers()->syncWithoutDetaching([$jordan->id=>['is_primary'=>true],$taylor->id=>['is_primary'=>false]]);
        $req2 = DeductionRequest::firstOrCreate(['show_id'=>$show2->id,'streamer_id'=>$jordan->id],['status'=>'pending']);
        DeductionRequestLine::firstOrCreate(['deduction_request_id'=>$req2->id,'inventory_item_id'=>$prizm->id],['inventory_location_id'=>$jordanLoc->id,'quantity_suggested'=>3,'quantity_approved'=>3,'unit_cost_snapshot'=>185.00,'line_total'=>555.00,'raw_description'=>'3x Prizm Basketball Hobby','ai_confidence'=>'high']);
        DeductionRequestLine::firstOrCreate(['deduction_request_id'=>$req2->id,'inventory_item_id'=>$optic->id],['inventory_location_id'=>$taylorLoc->id,'quantity_suggested'=>2,'quantity_approved'=>2,'unit_cost_snapshot'=>145.00,'line_total'=>290.00,'raw_description'=>'Optic Football box x2','ai_confidence'=>'medium']);
        DeductionRequestLine::firstOrCreate(['deduction_request_id'=>$req2->id,'inventory_item_id'=>$hoops->id],['inventory_location_id'=>$mainStorage->id,'quantity_suggested'=>4,'quantity_approved'=>4,'unit_cost_snapshot'=>22.00,'line_total'=>88.00,'raw_description'=>'Hoops blasters x4','ai_confidence'=>'low']);

        $show3 = Show::firstOrCreate(['title'=>'Mojo Break #43 — TCG Night'],['whatnot_channel_id'=>$channel?->id,'show_date'=>Carbon::now()->toDateString(),'units_sold'=>12,'gross_revenue'=>350.00,'whatnot_net'=>322.00,'tips'=>15.00,'import_source'=>'manual','status'=>'pending_review','created_by'=>1]);
        $show3->streamers()->syncWithoutDetaching([$taylor->id=>['is_primary'=>true]]);

        $show4 = Show::firstOrCreate(['title'=>'Mojo Break #40 — Football Frenzy'],['whatnot_channel_id'=>$channel?->id,'show_date'=>Carbon::now()->subDays(5)->toDateString(),'show_duration'=>150,'units_sold'=>8,'gross_revenue'=>780.00,'whatnot_net'=>717.60,'tips'=>20.00,'import_source'=>'manual','status'=>'pending_review','created_by'=>1]);
        $show4->streamers()->syncWithoutDetaching([$alex->id=>['is_primary'=>true],$taylor->id=>['is_primary'=>false]]);

        // Use explicit fixed demo week anchors so the seeder cannot accidentally
        // create overlapping batches when the current date falls into the same
        // week as one of the historical examples.
        $paidWeekStart = Carbon::create(2026, 8, 24)->startOfDay();
        $draftWeekStart = Carbon::create(2026, 8, 31)->startOfDay();

        $batch1 = WeeklyPayoutBatch::firstOrCreate(['week_start'=>$paidWeekStart->toDateString()],['week_end'=>$paidWeekStart->copy()->endOfWeek(Carbon::SUNDAY)->toDateString(),'status'=>'paid','total_payout'=>357.28,'created_by'=>1,'finalized_by'=>1,'finalized_at'=>$paidWeekStart->copy()->addDays(4)]);
        Payout::firstOrCreate(['show_id'=>$show1->id,'streamer_id'=>$jordan->id],['weekly_payout_batch_id'=>$batch1->id,'payout_type'=>'profit_share','gross_show_revenue'=>1140.80,'owner_fee_deducted'=>114.08,'tips_included'=>42.00,'calculated_payout'=>357.28,'calculation_notes'=>'Profit share demo payout','status'=>'paid']);

        $batch2 = WeeklyPayoutBatch::firstOrCreate(['week_start'=>$draftWeekStart->toDateString()],['week_end'=>$draftWeekStart->copy()->endOfWeek(Carbon::SUNDAY)->toDateString(),'status'=>'draft','total_payout'=>0,'created_by'=>1]);
        Payout::firstOrCreate(['show_id'=>$show2->id,'streamer_id'=>$jordan->id],['weekly_payout_batch_id'=>$batch2->id,'payout_type'=>'profit_share','gross_show_revenue'=>883.20,'owner_fee_deducted'=>88.32,'tips_included'=>14.00,'calculated_payout'=>278.57,'calculation_notes'=>'Profit share demo payout','status'=>'draft']);
        Payout::firstOrCreate(['show_id'=>$show2->id,'streamer_id'=>$taylor->id],['weekly_payout_batch_id'=>$batch2->id,'payout_type'=>'package','gross_show_revenue'=>883.20,'owner_fee_deducted'=>0,'tips_included'=>14.00,'calculated_payout'=>29.00,'calculation_notes'=>'Package rate $15.00 + $14.00 tips','status'=>'draft']);
        $batch2->recalculateTotal();

        $historicalShows = [
            [2,'Mojo Break #38 — Football Sunday',980.00,35.00,150,7,$jordan,'reconciled'],
            [3,'Mojo Break #35 — Baseball Weekend',1100.00,55.00,180,8,$jordan,'reconciled'],
            [4,'Mojo Break #31 — Hoops Thursday',750.00,22.00,135,6,$jordan,'reconciled'],
            [5,'Mojo Break #27 — Baseball Mega',1450.00,75.00,240,12,$jordan,'reconciled'],
            [6,'Mojo Break #23 — Football Night',890.00,30.00,150,8,$jordan,'reconciled'],
            [7,'Mojo Break #19 — Basketball Special',1200.00,45.00,180,9,$jordan,'reconciled'],
            [8,'Mojo Break #15 — Baseball Classics',960.00,40.00,165,7,$jordan,'reconciled'],
        ];

        foreach ($historicalShows as [$weeksBack,$title,$gross,$tips,$duration,$units,$streamer,$showStatus]) {
            $showDate = Carbon::now()->subWeeks($weeksBack)->startOfWeek(Carbon::MONDAY)->addDays(2);
            $historicShow = Show::firstOrCreate(['title'=>$title],['whatnot_channel_id'=>$channel?->id,'show_date'=>$showDate->toDateString(),'show_duration'=>$duration,'units_sold'=>$units,'gross_revenue'=>$gross,'whatnot_net'=>round($gross*.92,2),'tips'=>$tips,'import_source'=>'manual','status'=>$showStatus,'created_by'=>1]);
            $historicShow->streamers()->syncWithoutDetaching([$streamer->id=>['is_primary'=>true]]);
            $batchStart = $showDate->copy()->startOfWeek(Carbon::MONDAY);
            $overlap = WeeklyPayoutBatch::overlapping($batchStart->toDateString(), $batchStart->copy()->endOfWeek(Carbon::SUNDAY)->toDateString());
            $batch = $overlap ?: WeeklyPayoutBatch::create(['week_start'=>$batchStart->toDateString(),'week_end'=>$batchStart->copy()->endOfWeek(Carbon::SUNDAY)->toDateString(),'status'=>'paid','total_payout'=>0,'created_by'=>1,'finalized_by'=>1,'finalized_at'=>$batchStart->copy()->addDays(4)]);
            $payoutAmount = round($gross*.92*($streamer->payout_percentage/100)+$tips,2);
            Payout::firstOrCreate(['show_id'=>$historicShow->id,'streamer_id'=>$streamer->id],['weekly_payout_batch_id'=>$batch->id,'payout_type'=>$streamer->payout_type,'gross_show_revenue'=>$gross,'owner_fee_deducted'=>0,'tips_included'=>$tips,'calculated_payout'=>$payoutAmount,'calculation_notes'=>'Historical demo payout','status'=>'paid']);
            $batch->recalculateTotal();
        }
    }

    private function upsertItem(array $data): InventoryItem
    {
        $item = InventoryItem::withTrashed()->firstOrNew(['sku' => $data['sku']]);
        $item->fill($data);
        $item->deleted_at = null;
        $item->save();
        return $item;
    }
}
