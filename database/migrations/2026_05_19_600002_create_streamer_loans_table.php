<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('streamer_loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('streamer_id')->constrained()->cascadeOnDelete();
            $table->string('label');                          // e.g. "Equipment advance", "Signing bonus repayment"
            $table->decimal('original_amount', 10, 2);
            $table->decimal('weekly_repayment', 10, 2);
            $table->decimal('remaining_balance', 10, 2);
            $table->boolean('deduct_from_payout')->default(false);
            $table->string('status')->default('active');      // 'active' | 'paid_off'
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('streamer_loans');
    }
};
