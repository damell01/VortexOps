<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Models\Payout;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\WeeklyPayoutBatch;
use App\Models\WhatnotChannel;
use App\Support\PaymentStructure;
use Illuminate\Database\Seeder;

/** Small, valid fixtures used only by the payroll browser checks. */
class PayrollWorkspaceScreenshotSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) return;
        $this->call([DefaultDataSeeder::class, SuperAdminSeeder::class, StreamerPermissionsSeeder::class]);
        $channel = WhatnotChannel::firstOrCreate(['name' => 'Vortex Cards'], ['status' => 'active']);
        $business = Business::ordered()->first();
        PaymentStructure::saveDefaults('streamer', ['payout_type' => 'profit_share', 'payout_percentage' => 8,
            'payout_cadence' => 'weekly', 'hourly_rate' => 15, 'include_tips' => false]);
        $members = collect();
        foreach (['Alex', 'Angel', 'Bethany', 'Brandon', 'Camille', 'Jordan', 'Morgan', 'Taylor', 'Trey', 'Ty', 'Sam', 'Ash'] as $index => $name) {
            $kind = $index % 3 === 0 ? 'hourly' : ($index % 3 === 1 ? 'sales_split' : 'profit_share');
            $members->push(Streamer::create(['name' => $name, 'member_type' => 'streamer', 'streamer_type' => 'in_house',
                'status' => 'active', 'whatnot_channel_id' => $channel->id, 'business_id' => $business?->id,
                'payout_type' => $kind, 'hourly_rate' => 18, 'house_cut_percentage' => 10, 'sales_split_basis' => 'net',
                'payout_percentage' => 8, 'compensation_override_fields' => ['payout_type', 'hourly_rate', 'house_cut_percentage', 'sales_split_basis', 'payout_percentage']]));
        }
        $week = now()->startOfWeek();
        foreach ([1, 2, 3, 4] as $offset) {
            $start = $week->copy()->subWeeks($offset);
            $run = WeeklyPayoutBatch::create(['business_id' => $business?->id, 'week_start' => $start->toDateString(),
                'week_end' => $start->copy()->addDays(6)->toDateString(), 'status' => 'paid', 'total_payout' => 1700 + $offset * 150]);
            Payout::create(['weekly_payout_batch_id' => $run->id, 'streamer_id' => $members->first()->id,
                'payout_type' => 'hourly', 'calculated_payout' => $run->total_payout, 'status' => 'paid']);
        }
        foreach ($members->take(6) as $index => $member) {
            $show = Show::create(['whatnot_channel_id' => $channel->id, 'title' => ['Friday Night Hits','Store Claims','Pokemon Singles','Modern Hits','Chase Night','Weekend Breaks'][$index],
                'show_date' => $week->copy()->addDays($index)->toDateString(), 'status' => 'reconciled',
                'gross_revenue' => 900 + $index * 200, 'whatnot_net' => 800 + $index * 180, 'is_operational' => true]);
            $show->streamers()->attach($member->id, ['is_primary' => true]);
        }
    }
}
