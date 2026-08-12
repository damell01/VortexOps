<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Shell phase: enable only core operational modules by default.
    // Super-admins can turn on projects, reviews, and ai from App Settings.
    public function up(): void
    {
        $shellModules = json_encode(['streams', 'payouts', 'inventory', 'operations']);

        DB::table('settings')->updateOrInsert(
            ['key' => 'enabled_admin_modules'],
            ['value' => $shellModules, 'updated_at' => now(), 'created_at' => now()]
        );
    }

    public function down(): void
    {
        $allModules = json_encode(['projects', 'reviews', 'streams', 'payouts', 'inventory', 'operations', 'ai']);

        DB::table('settings')->updateOrInsert(
            ['key' => 'enabled_admin_modules'],
            ['value' => $allModules, 'updated_at' => now(), 'created_at' => now()]
        );
    }
};
