<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('fulfillment_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('station_id')->nullable()->constrained('fulfillment_stations')->nullOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['user_id', 'starts_at']);
        });

        Schema::create('fulfillment_flags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('show_id')->constrained('shows');
            $table->foreignId('run_id')->nullable()->constrained('fulfillment_runs')->nullOnDelete();
            $table->foreignId('buyer_id')->nullable()->constrained('fulfillment_run_buyers')->nullOnDelete();
            $table->foreignId('reported_by')->constrained('users');
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 32);
            $table->string('order_number')->nullable();
            $table->text('details');
            $table->string('status', 16)->default('open');
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution')->nullable();
            $table->timestamps();
            $table->index(['status', 'show_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fulfillment_flags');
        Schema::dropIfExists('fulfillment_shifts');
    }
};
