<?php

namespace Database\Seeders;

use App\Models\InventoryLocation;
use App\Models\Setting;
use App\Models\User;
use App\Models\WhatnotChannel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DefaultDataSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole      = Role::firstOrCreate(['name' => 'admin',       'guard_name' => 'web']);
        $superAdminRole = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'streamer', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'fulfillment', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'fulfillment_admin', 'guard_name' => 'web']);

        // Default admin account (dev/demo use — change password in production)
        $admin = User::firstOrCreate(
            ['email' => 'admin@vortexbreaks.com'],
            [
                'name'              => 'VortexOps Admin',
                'password'          => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $admin->syncRoles([$adminRole]);

        // Owner account — driven by APP_OWNER_EMAIL so it's always correct in production
        $ownerEmail = config('app.owner_email');
        if ($ownerEmail && $ownerEmail !== 'admin@vortexbreaks.com') {
            $owner = User::firstOrCreate(
                ['email' => $ownerEmail],
                [
                    'name'              => 'Owner',
                    'password'          => Hash::make(\Illuminate\Support\Str::random(32)),
                    'email_verified_at' => now(),
                ]
            );
            $owner->syncRoles([$superAdminRole]);

            $this->command->info("  Owner account: {$ownerEmail} (super_admin role)");
            $this->command->warn('  ⚠  Set the owner password via the password-reset link or php artisan tinker.');
        }

        $locations = [
            ['name' => 'Main Storage',       'type' => 'main_storage'],
            ['name' => 'Returned Inventory', 'type' => 'returned'],
            ['name' => 'Damaged Inventory',  'type' => 'damaged'],
            ['name' => 'Fulfillment Area',   'type' => 'fulfillment'],
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

        Setting::firstOrCreate(
            ['key' => 'enabled_admin_modules'],
            ['value' => json_encode(['streams', 'payouts', 'inventory', 'purchasing', 'operations', 'reporting'])]
        );
    }
}
