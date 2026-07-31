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
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('show_id')->constrained()->cascadeOnDelete();
            $table->foreignId('whatnot_order_id')->nullable()->constrained('whatnot_show_orders')->cascadeOnDelete();
            $table->string('buyer_username')->nullable();
            $table->dateTime('created_at_whatnot')->nullable();
            $table->integer('item_count')->nullable();
            $table->decimal('shipping_cost', 8, 2)->nullable();
            $table->decimal('weight_oz', 8, 2)->nullable();
            $table->json('dimensions_json')->nullable();
            $table->string('status')->nullable();
            $table->string('carrier')->nullable();
            $table->string('tracking_number')->nullable();
            $table->boolean('insurance_added')->default(false);
            $table->boolean('signature_required')->default(false);
            $table->json('raw_payload')->nullable();
            $table->timestamps();
            $table->index('show_id');
            $table->index('tracking_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
