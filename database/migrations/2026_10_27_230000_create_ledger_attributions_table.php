<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ledger_attributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatnot_ledger_entry_id')->unique()->constrained('whatnot_ledger_entries')->cascadeOnDelete();
            $table->string('category', 50);
            $table->string('target', 20);
            $table->foreignId('show_id')->nullable()->constrained('shows')->nullOnDelete();
            $table->foreignId('streamer_id')->nullable()->constrained('streamers')->nullOnDelete();
            $table->foreignId('business_id')->nullable()->constrained('businesses')->nullOnDelete();
            $table->string('status', 20)->default('pending');
            $table->text('reason');
            $table->foreignId('submitted_by')->nullable()->constrained('users');
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('ledger_attribution_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatnot_ledger_entry_id')->constrained('whatnot_ledger_entries')->cascadeOnDelete();
            $table->foreignId('actor_id')->constrained('users');
            $table->string('action', 30);
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('ledger_attribution_events');
        Schema::dropIfExists('ledger_attributions');
    }
};
