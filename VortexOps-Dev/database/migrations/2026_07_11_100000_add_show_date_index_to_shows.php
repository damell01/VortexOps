<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // shows.show_date is the default sort on the Show list and the filter
        // column for every dashboard widget's date-range query, but it was never
        // indexed. Composite (show_date, whatnot_channel_id) also covers the
        // channel-scoped date filters on the Shows page.
        Schema::table('shows', function (Blueprint $table) {
            if (! $this->indexExists('shows', 'shows_show_date_channel_idx')) {
                $table->index(['show_date', 'whatnot_channel_id'], 'shows_show_date_channel_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('shows', function (Blueprint $table) {
            if ($this->indexExists('shows', 'shows_show_date_channel_idx')) {
                $table->dropIndex('shows_show_date_channel_idx');
            }
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        try {
            $driver = Schema::getConnection()->getDriverName();

            if ($driver === 'sqlite') {
                return collect(DB::select("PRAGMA index_list('{$table}')"))
                    ->contains(fn ($row) => ($row->name ?? null) === $index);
            }

            // MySQL / MariaDB
            return ! empty(DB::select(
                "SHOW INDEX FROM `{$table}` WHERE Key_name = ?",
                [$index]
            ));
        } catch (\Throwable) {
            return false;
        }
    }
};
