<?php

namespace Database\Seeders;

use App\Models\InventoryLocation;
use App\Models\User;
use App\Models\WhatnotChannel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DefaultDataSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole    = Role::firstOrCreate(['name' => 'admin',    'guard_name' => 'web']);
        $streamerRole = Role::firstOrCreate(['name' => 'streamer', 'guard_name' => 'web']);

        $admin = User::firstOrCreate(
            ['email' => 'admin@vortexbreaks.com'],
            [
                'name' => 'VortexOps Admin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $admin->syncRoles([$adminRole]);

        $locations = [
            ['name' => 'Main Storage', 'type' => 'main_storage'],
            ['name' => 'Returned Inventory', 'type' => 'returned'],
            ['name' => 'Damaged Inventory', 'type' => 'damaged'],
            ['name' => 'Fulfillment Area', 'type' => 'fulfillment'],
        ];

        foreach ($locations as $loc) {
            InventoryLocation::firstOrCreate(
                ['name' => $loc['name']],
                ['type' => $loc['type'], 'status' => 'active']
            );
        }

        WhatnotChannel::firstOrCreate(
            ['name' => 'Vortex Main Channel'],
            ['status' => 'active']
        );
    }
}
