<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A difference found by "Changed after payout", carried onto a later draft run
 * as an adjustment line. The line itself is the payout; this row ties it back
 * to the show, person and run it settles, so the check counts it as paid.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pay_change_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('show_id')->constrained('shows')->cascadeOnDelete();
            $table->foreignId('streamer_id')->constrained('streamers')->cascadeOnDelete();
            $table->foreignId('source_run_id')->nullable()->constrained('weekly_payout_batches')->nullOnDelete();
            $table->foreignId('target_run_id')->constrained('weekly_payout_batches')->cascadeOnDelete();
            $table->foreignId('payout_id')->constrained('payouts')->cascadeOnDelete();
            $table->decimal('paid', 10, 2);
            $table->decimal('now', 10, 2);
            $table->decimal('amount', 10, 2);
            $table->text('note')->nullable();
            $table->foreignId('settled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['show_id', 'streamer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pay_change_settlements');
    }
};
