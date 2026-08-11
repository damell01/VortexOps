<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('pallets')->where('status', 'open')->update(['status' => 'staged']);
        DB::table('pallets')->where('status', 'pending')->update(['status' => 'staged']);
        DB::table('pallets')->where('status', 'shipped')->update(['status' => 'staging']);
        DB::table('pallets')->where('status', 'received')->update(['status' => 'processed']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('pallets')->where('status', 'staged')->where('status', 'pending')->update(['status' => 'open']);
        DB::table('pallets')->where('status', 'received')->update(['status' => 'received']);
        DB::table('pallets')->where('status', 'processed')->update(['status' => 'received']);
    }
};
