<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatnot_show_id')->constrained('whatnot_shows')->cascadeOnDelete();
            $table->foreignId('streamer_id')->constrained('streamers')->cascadeOnDelete();
            $table->foreignId('weekly_payout_batch_id')->nullable()->constrained('weekly_payout_batches')->nullOnDelete();
            $table->enum('payout_type', ['profit_share', 'package', 'hourly', 'flat_rate']);
            $table->decimal('gross_show_revenue', 12, 2)->default(0);
            $table->decimal('owner_fee_deducted', 12, 2)->default(0);
            $table->decimal('tips_included', 12, 2)->default(0);
            $table->decimal('calculated_payout', 12, 2)->default(0);
            $table->text('calculation_notes')->nullable();
            $table->enum('status', ['draft', 'approved', 'paid'])->default('draft');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payouts');
    }
};
