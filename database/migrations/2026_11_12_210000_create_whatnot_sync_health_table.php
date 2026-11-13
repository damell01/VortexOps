<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('whatnot_sync_health', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('whatnot_channel_id');
            $table->string('mode', 24);
            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->boolean('last_run_ok')->nullable();
            $table->decimal('duration_seconds', 10, 2)->nullable();
            $table->unsignedInteger('orders_pages')->nullable();
            $table->boolean('orders_incomplete')->nullable();
            $table->json('errors')->nullable();
            $table->unsignedInteger('consecutive_incomplete')->default(0);
            $table->timestamp('last_alert_at')->nullable();
            $table->timestamps();
            $table->unique(['whatnot_channel_id', 'mode']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatnot_sync_health');
    }
};
