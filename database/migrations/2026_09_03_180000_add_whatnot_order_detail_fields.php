<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatnot_show_orders', function (Blueprint $table) {
            $table->string('whatnot_shipment_id')->nullable()->index();
            $table->text('whatnot_shipment_url')->nullable();
            $table->text('whatnot_order_detail_url')->nullable();
            $table->timestamp('ordered_at_whatnot')->nullable();
            $table->string('product_category')->nullable();
            $table->string('show_category')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('whatnot_show_orders', function (Blueprint $table) {
            $table->dropIndex(['whatnot_shipment_id']);
            $table->dropColumn([
                'whatnot_shipment_id',
                'whatnot_shipment_url',
                'whatnot_order_detail_url',
                'ordered_at_whatnot',
                'product_category',
                'show_category',
            ]);
        });
    }
};
