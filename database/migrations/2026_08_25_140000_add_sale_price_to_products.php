<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What an item is meant to sell for.
 *
 * The costing side was complete — unit_cost on receipt, average_cost
 * maintained as a weighted average — and the other half of the question was
 * missing entirely: nothing recorded what the item is supposed to fetch, so
 * nothing could say what a case is worth breaking.
 *
 * Only the target is stored. Margin potential is the difference between it
 * and the cost basis, and a stored copy of a subtraction goes stale the first
 * time a receipt moves the weighted average.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('sale_price', 10, 2)
                ->nullable()
                ->after('average_cost');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('sale_price');
        });
    }
};
