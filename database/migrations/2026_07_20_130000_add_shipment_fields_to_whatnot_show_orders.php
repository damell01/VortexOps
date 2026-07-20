<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whatnot's Shipments tab (distinct from the Orders tab already scraped)
 * carries per-shipment weight, box dimensions, and carrier/service — not
 * available anywhere else. Columns are nullable and populated opportunistically
 * as the shipments scraper matches rows to existing orders by whatnot_order_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatnot_show_orders', function (Blueprint $table) {
            $table->decimal('shipment_weight_oz', 6, 2)->nullable()->after('shipping_status');
            $table->decimal('box_length_in', 6, 2)->nullable()->after('shipment_weight_oz');
            $table->decimal('box_width_in', 6, 2)->nullable()->after('box_length_in');
            $table->decimal('box_height_in', 6, 2)->nullable()->after('box_width_in');
            $table->string('shipping_carrier')->nullable()->after('box_height_in');
            $table->string('shipping_service')->nullable()->after('shipping_carrier');
            $table->timestamp('shipment_synced_at')->nullable()->after('shipping_service');
        });
    }

    public function down(): void
    {
        Schema::table('whatnot_show_orders', function (Blueprint $table) {
            $table->dropColumn([
                'shipment_weight_oz',
                'box_length_in',
                'box_width_in',
                'box_height_in',
                'shipping_carrier',
                'shipping_service',
                'shipment_synced_at',
            ]);
        });
    }
};
