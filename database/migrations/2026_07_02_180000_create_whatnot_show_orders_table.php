<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatnot_show_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('show_id')->nullable()->constrained('shows')->nullOnDelete();
            $table->string('whatnot_order_id')->nullable()->index();
            $table->string('whatnot_show_url')->nullable();
            $table->string('buyer_username')->nullable()->index();
            $table->string('buyer_display_name')->nullable();
            $table->unsignedSmallInteger('lot_number')->nullable();
            $table->string('item_name')->nullable();
            $table->text('item_description')->nullable();
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->decimal('unit_price', 8, 2)->nullable();
            $table->decimal('total_price', 8, 2)->nullable();
            $table->string('status')->default('completed')->index();
            $table->date('show_date')->nullable()->index();
            $table->json('raw_data')->nullable();
            $table->timestamps();

            $table->unique(['show_id', 'whatnot_order_id'], 'wnorders_show_order_unique');
            $table->index(['show_id', 'buyer_username'], 'wnorders_show_buyer_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatnot_show_orders');
    }
};
