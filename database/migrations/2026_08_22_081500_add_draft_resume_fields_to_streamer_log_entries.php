<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('streamer_log_entries', function (Blueprint $table) {
            if (! Schema::hasColumn('streamer_log_entries', 'draft_step')) {
                $table->unsignedTinyInteger('draft_step')->default(1)->after('notes');
            }
            if (! Schema::hasColumn('streamer_log_entries', 'draft_saved_at')) {
                $table->timestamp('draft_saved_at')->nullable()->after('draft_step');
            }
        });
    }

    public function down(): void
    {
        Schema::table('streamer_log_entries', function (Blueprint $table) {
            $columns = [];
            if (Schema::hasColumn('streamer_log_entries', 'draft_saved_at')) $columns[] = 'draft_saved_at';
            if (Schema::hasColumn('streamer_log_entries', 'draft_step')) $columns[] = 'draft_step';
            if ($columns) $table->dropColumn($columns);
        });
    }
};
