<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatnot_show_orders', function (Blueprint $table) {
            // Link to normalized buyer record
            $table->foreignId('whatnot_buyer_id')->nullable()->after('show_id')
                ->constrained('whatnot_buyers')->nullOnDelete();

            // Link to internal inventory for product mapping
            $table->foreignId('inventory_item_id')->nullable()->after('whatnot_buyer_id')
                ->constrained('inventory_items')->nullOnDelete();

            // Financial breakdown
            $table->decimal('shipping_amount', 8, 2)->nullable()->after('total_price');
            $table->decimal('tax_amount', 8, 2)->nullable()->after('shipping_amount');
            $table->decimal('fees_amount', 8, 2)->nullable()->after('tax_amount');
            $table->decimal('net_amount', 8, 2)->nullable()->after('fees_amount');

            // Fulfilment
            $table->string('tracking_number')->nullable()->after('status');
            $table->string('shipping_status')->nullable()->after('tracking_number');
        });

        Schema::table('whatnot_show_orders', function (Blueprint $table) {
            $table->index('whatnot_buyer_id');
            $table->index('inventory_item_id');
        });
    }

    public function down(): void
    {
        Schema::table('whatnot_show_orders', function (Blueprint $table) {
            $table->dropIndex(['whatnot_buyer_id']);
            $table->dropIndex(['inventory_item_id']);
            $table->dropForeign(['whatnot_buyer_id']);
            $table->dropForeign(['inventory_item_id']);
            $table->dropColumn([
                'whatnot_buyer_id', 'inventory_item_id',
                'shipping_amount', 'tax_amount', 'fees_amount', 'net_amount',
                'tracking_number', 'shipping_status',
            ]);
        });
    }
};
