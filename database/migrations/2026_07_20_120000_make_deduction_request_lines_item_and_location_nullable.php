<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A deduction line legitimately exists in an "unmatched, needs manual
 * review" state before ops assigns an inventory item and location to it —
 * the review UI already has dedicated "⚠ Unmatched" styling for exactly
 * this. But both foreign keys were created NOT NULL, so any code path that
 * creates a line before an item/location is picked (e.g. "Map Items
 * Manually" on a show) has always failed with a constraint violation the
 * moment it actually ran.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deduction_request_lines', function (Blueprint $table) {
            $table->foreignId('inventory_item_id')->nullable()->change();
            $table->foreignId('inventory_location_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('deduction_request_lines', function (Blueprint $table) {
            $table->foreignId('inventory_item_id')->nullable(false)->change();
            $table->foreignId('inventory_location_id')->nullable(false)->change();
        });
    }
};
