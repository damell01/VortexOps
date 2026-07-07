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
        Schema::create('streamer_log_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('show_id')->unique()->constrained('shows')->cascadeOnDelete();
            $table->foreignId('streamer_id')->constrained('streamers')->cascadeOnDelete();
            $table->boolean('hard_copy')->default(false);
            $table->decimal('hours_streamed', 5, 2)->nullable();
            $table->integer('number_of_shipments')->nullable();
            $table->integer('number_of_packages_over_500')->default(0);
            $table->decimal('pwe_pay', 10, 2)->default(0);
            $table->decimal('hourly_pay', 10, 2)->default(0);
            $table->decimal('profit_share_amount', 10, 2)->default(0);
            $table->boolean('profit_share_paid')->default(false);
            $table->decimal('tips_paid', 10, 2)->default(0);
            $table->decimal('total_due', 10, 2)->default(0);
            $table->decimal('total_paid', 10, 2)->default(0);
            $table->decimal('business_net_rev', 10, 2)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['streamer_id', 'reviewed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('streamer_log_entries');
    }
};
