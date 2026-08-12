<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payouts', function (Blueprint $table) {
            if (! Schema::hasIndex('payouts', 'payouts_show_id_index')) {
                $table->index('show_id');
            }
        });

        if (Schema::hasTable('deduction_request_lines')) {
            Schema::table('deduction_request_lines', function (Blueprint $table) {
                if (! Schema::hasIndex('deduction_request_lines', 'deduction_request_lines_inventory_item_id_index')) {
                    $table->index('inventory_item_id');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('payouts', function (Blueprint $table) {
            $table->dropIndexIfExists('payouts_show_id_index');
        });

        if (Schema::hasTable('deduction_request_lines')) {
            Schema::table('deduction_request_lines', function (Blueprint $table) {
                $table->dropIndexIfExists('deduction_request_lines_inventory_item_id_index');
            });
        }
    }
};
