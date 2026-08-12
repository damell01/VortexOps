<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $user = User::firstOrCreate(
            ['email' => 'dev@vortexbreaks.com'],
            [
                'name'               => 'Dev (Super Admin)',
                'password'           => Hash::make('devpassword'),
                'email_verified_at'  => now(),
            ]
        );

        $user->syncRoles([$role]);

        $this->command->info('');
        $this->command->info('  Super Admin account ready:');
        $this->command->info('  Email:    dev@vortexbreaks.com');
        $this->command->info('  Password: devpassword');
        $this->command->info('  Role:     super_admin');
        $this->command->info('');
    }
}
