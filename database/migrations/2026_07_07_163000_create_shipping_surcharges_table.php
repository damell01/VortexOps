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
        Schema::create('shipping_surcharges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('show_id')->constrained('shows')->cascadeOnDelete();
            $table->foreignId('streamer_id')->constrained('streamers')->cascadeOnDelete();
            $table->integer('package_count');
            $table->decimal('rate_per_package', 10, 2);
            $table->decimal('threshold_amount', 10, 2)->default(500.00);
            $table->decimal('total_amount', 10, 2);
            $table->boolean('deducted_from_payout')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['show_id', 'streamer_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipping_surcharges');
    }
};
