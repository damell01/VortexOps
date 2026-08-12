<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('show_change_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('show_id')->constrained('shows')->cascadeOnDelete();
            $table->string('field_name'); // e.g., 'net_margin', 'cost', 'shipping', 'status'
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->string('changed_by')->nullable(); // user email or process name like 'Whatnot Import'
            $table->string('source')->default('manual'); // 'manual', 'whatnot_import', 'cron', 'api'
            $table->text('notes')->nullable(); // Optional context about the change
            $table->timestamp('created_at')->useCurrent();

            $table->index('show_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('show_change_logs');
    }
};
