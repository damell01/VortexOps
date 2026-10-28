<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('inventory_pallet_review_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('pallet_line_id')->constrained('pallet_lines')->cascadeOnDelete();
            $table->string('fingerprint', 64);
            $table->timestamp('acknowledged_at');
            $table->timestamps();
            $table->unique(['user_id', 'pallet_line_id']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('inventory_pallet_review_states');
    }
};
