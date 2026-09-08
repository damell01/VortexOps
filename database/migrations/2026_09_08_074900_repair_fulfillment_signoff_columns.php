<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('streamer_log_entries')) {
            return;
        }

        Schema::table('streamer_log_entries', function (Blueprint $table) {
            if (! Schema::hasColumn('streamer_log_entries', 'fulfillment_reviewed_by')) {
                $table->unsignedBigInteger('fulfillment_reviewed_by')->nullable()->after('reviewed_at');
            }

            if (! Schema::hasColumn('streamer_log_entries', 'fulfillment_reviewed_at')) {
                $table->timestamp('fulfillment_reviewed_at')->nullable()->after('fulfillment_reviewed_by');
            }
        });
    }

    public function down(): void
    {
        // This is a repair migration for databases where the original July
        // migration was only partially applied. Do not remove a valid signoff
        // column on rollback and risk discarding fulfillment audit history.
    }
};
