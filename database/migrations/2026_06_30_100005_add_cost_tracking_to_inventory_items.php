<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->foreignId('preferred_vendor_id')
                  ->nullable()
                  ->after('is_active')
                  ->constrained('vendors')
                  ->nullOnDelete();

            $table->decimal('average_cost', 10, 4)
                  ->default(0)
                  ->after('unit_cost')
                  ->comment('Weighted average cost across all receipts');

            $table->decimal('total_units_received', 12, 2)
                  ->default(0)
                  ->after('average_cost')
                  ->comment('Cumulative units received via receiving workflow');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropForeign(['preferred_vendor_id']);
            $table->dropColumn(['preferred_vendor_id', 'average_cost', 'total_units_received']);
        });
    }
};
