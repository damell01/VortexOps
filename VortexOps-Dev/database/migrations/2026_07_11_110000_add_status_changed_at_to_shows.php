<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('shows', 'status_changed_at')) {
            Schema::table('shows', function (Blueprint $table) {
                // When the show last moved to its current status — powers the
                // "time in status" aging on the pipeline board.
                $table->timestamp('status_changed_at')->nullable()->after('status');
            });

            // Seed existing rows with a sensible starting point so the board isn't
            // all "age unknown" on first deploy.
            DB::table('shows')->whereNull('status_changed_at')->update([
                'status_changed_at' => DB::raw('COALESCE(updated_at, created_at)'),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('shows', 'status_changed_at')) {
            Schema::table('shows', function (Blueprint $table) {
                $table->dropColumn('status_changed_at');
            });
        }
    }
};
