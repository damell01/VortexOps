<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Production already has this unique key from an earlier migration/build.
        // Make this migration safe to rerun after a partial failure instead of
        // trying to create the same MySQL index twice.
        if (! $this->indexExists('shows', 'shows_whatnot_show_id_unique')) {
            Schema::table('shows', function (Blueprint $table): void {
                $table->unique('whatnot_show_id', 'shows_whatnot_show_id_unique');
            });
        }

        if (! Schema::hasTable('whatnot_show_aliases')) {
            Schema::create('whatnot_show_aliases', function (Blueprint $table): void {
                $table->id();
                $table->uuid('duplicate_whatnot_show_id')->unique();
                $table->foreignId('canonical_show_id')->constrained('shows')->cascadeOnDelete();
                $table->string('reason')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('whatnot_show_aliases');

        if ($this->indexExists('shows', 'shows_whatnot_show_id_unique')) {
            Schema::table('shows', function (Blueprint $table): void {
                $table->dropUnique('shows_whatnot_show_id_unique');
            });
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        $database = DB::getDatabaseName();

        return DB::table('information_schema.statistics')
            ->where('table_schema', $database)
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();
    }
};
