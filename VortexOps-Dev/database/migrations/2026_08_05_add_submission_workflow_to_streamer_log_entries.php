<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('streamer_log_entries', function (Blueprint $table) {
            $table->datetime('submitted_at')->nullable()->after('streamer_reviewed_at');
            $table->datetime('locked_at')->nullable()->after('submitted_at');
            $table->integer('edit_window_minutes')->default(120)->after('locked_at');
            $table->datetime('approval_requested_at')->nullable()->after('edit_window_minutes');
            $table->string('approval_status')->default('pending')->after('approval_requested_at');
            $table->text('approval_notes')->nullable()->after('approval_status');
        });
    }

    public function down(): void
    {
        Schema::table('streamer_log_entries', function (Blueprint $table) {
            $table->dropColumn([
                'submitted_at',
                'locked_at',
                'edit_window_minutes',
                'approval_requested_at',
                'approval_status',
                'approval_notes',
            ]);
        });
    }
};
