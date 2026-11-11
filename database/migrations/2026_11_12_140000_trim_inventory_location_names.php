<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Remove leading/trailing spaces from location names (" Main Warehouse"),
 * which made name-based filters such as the Inventory Report's match nothing.
 * Only names that actually have such spaces are touched.
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('inventory_locations')) return;
        DB::table('inventory_locations')->whereRaw('name <> TRIM(name)')->orderBy('id')->get(['id', 'name'])
            ->each(fn ($row) => DB::table('inventory_locations')->where('id', $row->id)->update(['name' => trim($row->name)]));
    }

    public function down(): void
    {
        // Nothing to restore: the spaces carried no meaning.
    }
};
