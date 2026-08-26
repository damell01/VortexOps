<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A streamer asking to change a report they have already filed.
 *
 * The edit window closes two hours after submission, and after that the only
 * route back was to find an admin and ask them in person to press Request
 * Changes. So a wrong number stayed wrong: the person who spotted it had no
 * way to say so inside the app, and the person who could fix it never heard.
 *
 * Deliberately not a status of its own. The report is still submitted and
 * still counts; this is a flag on top saying somebody wants it reopened.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('streamer_log_entries', function (Blueprint $table) {
            if (! Schema::hasColumn('streamer_log_entries', 'revision_requested_at')) {
                $table->timestamp('revision_requested_at')->nullable()->after('locked_at');
            }

            if (! Schema::hasColumn('streamer_log_entries', 'revision_reason')) {
                $table->text('revision_reason')->nullable()->after('revision_requested_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('streamer_log_entries', function (Blueprint $table) {
            foreach (['revision_requested_at', 'revision_reason'] as $column) {
                if (Schema::hasColumn('streamer_log_entries', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
