<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pallet_lines', function (Blueprint $table) {
            $table->enum('line_status', ['pending', 'received'])->default('pending')->after('inventory_item_id');
            $table->decimal('preflight_cost', 12, 4)->nullable()->after('unit_cost')->comment('Optional cost estimate from packing slip (does not affect real unit_cost until received)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pallet_lines', function (Blueprint $table) {
            $table->dropColumn(['line_status', 'preflight_cost']);
        });
    }
};
