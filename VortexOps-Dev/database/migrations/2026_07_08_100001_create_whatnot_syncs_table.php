<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatnot_syncs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatnot_channel_id')->nullable()->constrained('whatnot_channels')->nullOnDelete();
            $table->enum('type', ['incremental', 'last_30_days', 'full'])->default('incremental');
            $table->enum('status', ['running', 'completed', 'failed'])->default('running')->index();
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->unsignedInteger('shows_created')->default(0);
            $table->unsignedInteger('shows_updated')->default(0);
            $table->unsignedInteger('orders_created')->default(0);
            $table->unsignedInteger('orders_updated')->default(0);
            $table->unsignedInteger('buyers_created')->default(0);
            $table->unsignedInteger('buyers_updated')->default(0);
            $table->unsignedInteger('items_created')->default(0);
            $table->unsignedInteger('items_updated')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->json('errors')->nullable();
            $table->json('summary')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatnot_syncs');
    }
};
