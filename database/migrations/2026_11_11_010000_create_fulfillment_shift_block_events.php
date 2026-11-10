<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('fulfillment_shift_block_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_block_id')->constrained('fulfillment_shift_blocks')->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 40);
            $table->foreignId('employee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('details')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['shift_block_id', 'created_at'], 'fulfillment_shift_event_history_idx');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('fulfillment_shift_block_events');
    }
};
