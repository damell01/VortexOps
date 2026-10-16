<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pay_run_report_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('weekly_payout_batch_id')->constrained()->restrictOnDelete();
            $table->string('stage', 20);
            $table->json('payload');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['weekly_payout_batch_id', 'stage'], 'pay_run_report_stage_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pay_run_report_snapshots');
    }
};
