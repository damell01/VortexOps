<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shows', function (Blueprint $table) {
            $table->boolean('is_operational')->default(true)->index()->after('import_source');
        });

        // Old scraper/backfill rows are useful history, but they should not wake
        // up as fake work just because the new workflow can now diagnose them.
        // Preserve anything somebody actually started working by requiring that
        // no streamer report exists before archiving an older Whatnot import.
        DB::table('shows')
            ->where('import_source', 'auto_whatnot')
            ->where('show_date', '<', now()->subDays(14)->toDateString())
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('streamer_log_entries')
                    ->whereColumn('streamer_log_entries.show_id', 'shows.id');
            })
            ->update(['is_operational' => false]);
    }

    public function down(): void
    {
        Schema::table('shows', function (Blueprint $table) {
            $table->dropIndex(['is_operational']);
            $table->dropColumn('is_operational');
        });
    }
};
