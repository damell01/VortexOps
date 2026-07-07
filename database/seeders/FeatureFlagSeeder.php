<?php

namespace Database\Seeders;

use App\Models\FeatureFlag;
use Illuminate\Database\Seeder;

class FeatureFlagSeeder extends Seeder
{
    public function run(): void
    {
        $flags = [
            [
                'key'         => 'inventory_pallets',
                'label'       => 'Pallet Tracking',
                'description' => 'Enables pallet-level inventory hierarchy',
                'enabled'     => true,
            ],
            [
                'key'         => 'inventory_locations',
                'label'       => 'Sub-Locations',
                'description' => 'Enables granular location tracking below warehouse level',
                'enabled'     => true,
            ],
            [
                'key'         => 'ledger',
                'label'       => 'Finance Ledger',
                'description' => 'Full transaction ledger and reporting',
                'enabled'     => true,
            ],
            [
                'key'         => 'analytics',
                'label'       => 'Streamer Analytics',
                'description' => 'Performance analytics and SPH reporting',
                'enabled'     => true,
            ],
            [
                'key'         => 'shipping_surcharge',
                'label'       => 'Shipping Surcharge Tracking',
                'description' => 'Auto-charge streamers for packages over configurable threshold',
                'enabled'     => true,
            ],
            [
                'key'         => 'profit_share_packet',
                'label'       => 'Profit Share Packet',
                'description' => 'Monthly profit share packet generation',
                'enabled'     => true,
            ],
            [
                'key'         => 'streamer_log',
                'label'       => 'Streamer Log',
                'description' => 'Post-show streamer log review workflow',
                'enabled'     => true,
            ],
            [
                'key'         => 'statements',
                'label'       => 'Statements & Reports',
                'description' => 'Printable streamer statements and ledger reports',
                'enabled'     => true,
            ],
        ];

        foreach ($flags as $flag) {
            FeatureFlag::updateOrCreate(
                ['key' => $flag['key']],
                [
                    'label'       => $flag['label'],
                    'description' => $flag['description'],
                    'enabled'     => $flag['enabled'],
                ]
            );
        }
    }
}
