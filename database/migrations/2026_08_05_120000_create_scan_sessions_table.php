<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scan_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['inventory', 'receiving', 'shipping', 'lookup']);
            $table->unsignedBigInteger('context_id')->nullable();
            $table->string('context_type')->nullable(); // 'Pallet', 'Shipment', 'Order'
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('item_count')->default(0);
            $table->enum('status', ['active', 'completed', 'cancelled'])->default('active');
            $table->json('metadata')->nullable(); // {location_id, duration_seconds, etc}
            $table->timestamps();

            $table->index(['user_id', 'started_at']);
            $table->index(['type', 'status']);
            $table->index('context_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scan_sessions');
    }
};
