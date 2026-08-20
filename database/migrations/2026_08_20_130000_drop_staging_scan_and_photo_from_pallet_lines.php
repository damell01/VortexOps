<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Take back two columns that were added on a wrong assumption.
 *
 * They were meant to let a staged line carry the barcode and photo of the box
 * it describes. But staging is done from the packing slip, before the pallet
 * lands — there is no box there to scan or photograph, so neither column could
 * ever be filled in the flow it was built for. A photo belongs at receiving,
 * where the box is open and in somebody's hands, and there it goes straight
 * onto the product.
 *
 * Guarded on both sides because the columns only exist where the earlier
 * migration ran before it was withdrawn: a fresh database never had them.
 */
return new class extends Migration
{
    public function up(): void
    {
        $drop = array_values(array_filter(
            ['barcode', 'photo_path'],
            fn (string $column) => Schema::hasColumn('pallet_lines', $column),
        ));

        if ($drop === []) {
            return;
        }

        Schema::table('pallet_lines', fn (Blueprint $table) => $table->dropColumn($drop));
    }

    public function down(): void
    {
        // Deliberately not restoring them. Rolling this back would put back
        // columns nothing reads, on a screen that cannot fill them.
    }
};
