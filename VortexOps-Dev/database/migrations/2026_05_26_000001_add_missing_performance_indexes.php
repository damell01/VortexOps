<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deduction_requests', function (Blueprint $table) {
            $table->index(['show_id', 'status'], 'dr_show_status_idx');
        });

        Schema::table('payouts', function (Blueprint $table) {
            $table->index('show_id', 'payouts_show_id_idx');
        });

        Schema::table('inventory_stock', function (Blueprint $table) {
            $table->index('inventory_location_id', 'istock_location_idx');
        });
    }

    public function down(): void
    {
        Schema::table('deduction_requests', function (Blueprint $table) {
            $table->dropIndex('dr_show_status_idx');
        });

        Schema::table('payouts', function (Blueprint $table) {
            $table->dropIndex('payouts_show_id_idx');
        });

        Schema::table('inventory_stock', function (Blueprint $table) {
            $table->dropIndex('istock_location_idx');
        });
    }
};
