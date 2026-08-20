<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Let a line that never arrived be reported as missing.
 *
 * missing_item_reports required an inventory_item_id, which assumes the thing
 * being reported exists in inventory. For a shortfall that is exactly backwards:
 * a line is staged from the packing slip, the box never turns up, so it is never
 * scanned and no product is ever created — and that is the case you most want to
 * record. Marking it short threw an integrity violation instead.
 *
 * The line is added alongside, because "one Test 2 short" is only meaningful if
 * you can say which line it was. It also survives the product being merged or
 * deleted later, which the item id alone does not.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('missing_item_reports', function (Blueprint $table) {
            $table->foreignId('pallet_line_id')
                ->nullable()
                ->after('pallet_id')
                ->constrained('pallet_lines')
                ->nullOnDelete();

            // What the slip called it. The only description there is when
            // nothing was ever linked.
            $table->string('description')->nullable()->after('pallet_line_id');
        });

        // Dropping the constraint before relaxing the column: MySQL will not
        // change a column an index depends on while that index is in place.
        Schema::table('missing_item_reports', function (Blueprint $table) {
            $table->dropForeign(['inventory_item_id']);
        });

        Schema::table('missing_item_reports', function (Blueprint $table) {
            $table->foreignId('inventory_item_id')->nullable()->change();
        });

        Schema::table('missing_item_reports', function (Blueprint $table) {
            $table->foreign('inventory_item_id')->references('id')->on('products')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('missing_item_reports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pallet_line_id');
            $table->dropColumn('description');
        });
    }
};
