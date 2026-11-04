<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('fulfillment_stations', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('fulfillment_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('show_id')->constrained('shows');
            $table->foreignId('station_id')->constrained('fulfillment_stations');
            $table->string('status', 16)->default('ready');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            // MySQL and SQLite both permit multiple NULLs in a unique index.
            // Set station_occupied_id only while actively packing.
            $table->unsignedBigInteger('station_occupied_id')->nullable()->unique();
            $table->timestamps();
            $table->index(['show_id', 'status']);
        });

        Schema::create('fulfillment_run_packers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')->constrained('fulfillment_runs')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->unique(['run_id', 'user_id']);
        });

        Schema::create('fulfillment_run_buyers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')->constrained('fulfillment_runs')->cascadeOnDelete();
            $table->string('buyer_username');
            $table->string('letter', 12);
            $table->json('order_ids')->nullable();
            $table->unsignedInteger('item_count')->default(0);
            $table->string('status', 16)->default('waiting');
            $table->foreignId('packed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('packing_started_at')->nullable();
            $table->timestamp('packed_at')->nullable();
            $table->timestamps();
            $table->unique(['run_id', 'letter']);
            $table->unique(['run_id', 'buyer_username']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fulfillment_run_buyers');
        Schema::dropIfExists('fulfillment_run_packers');
        Schema::dropIfExists('fulfillment_runs');
        Schema::dropIfExists('fulfillment_stations');
    }
};
