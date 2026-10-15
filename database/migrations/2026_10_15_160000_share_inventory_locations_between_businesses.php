<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A location can be shared by more than one business. The businesses a
 * shared location belongs to move to a pivot; inventory_locations.business_id
 * stays only as a streamer's own location's business (for listing).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_inventory_location', function (Blueprint $table) {
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('inventory_location_id')->constrained('inventory_locations')->cascadeOnDelete();
            $table->primary(['business_id', 'inventory_location_id']);
        });

        $rows = DB::table('inventory_locations')->whereNull('streamer_id')->whereNotNull('business_id')->get(['id', 'business_id']);
        foreach ($rows as $row) {
            DB::table('business_inventory_location')->insert(['business_id' => $row->business_id, 'inventory_location_id' => $row->id]);
        }
        DB::table('inventory_locations')->whereNull('streamer_id')->update(['business_id' => null]);
    }

    public function down(): void
    {
        foreach (DB::table('business_inventory_location')->get() as $row) {
            DB::table('inventory_locations')->where('id', $row->inventory_location_id)->whereNull('business_id')->update(['business_id' => $row->business_id]);
        }
        Schema::dropIfExists('business_inventory_location');
    }
};
