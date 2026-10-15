<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A location can belong to a business. A business with storage of its own
 * (Estill Distribution) keeps its streamers drawing from that instead of
 * the shared Main Storage. See Streamer::inventoryLocations().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_locations', function (Blueprint $table) {
            $table->foreignId('business_id')->nullable()->constrained('businesses')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_locations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('business_id');
        });
    }
};
