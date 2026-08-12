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
        Schema::table('streamer_log_entries', function (Blueprint $table) {
            // pending → streamer_reviewed → admin_approved
            $table->string('status')->default('pending')->after('show_id');
            $table->decimal('gross_revenue', 10, 2)->nullable()->after('notes');
            $table->decimal('product_cost', 10, 2)->nullable()->after('gross_revenue');
            $table->timestamp('streamer_reviewed_at')->nullable()->after('reviewed_at');
        });
    }

    public function down(): void
    {
        Schema::table('streamer_log_entries', function (Blueprint $table) {
            $table->dropColumn(['status', 'gross_revenue', 'product_cost', 'streamer_reviewed_at']);
        });
    }
};
