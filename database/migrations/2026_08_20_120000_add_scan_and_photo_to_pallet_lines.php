<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Let a staged line carry the barcode and the photo of the thing itself.
 *
 * Staging happens for items that are not in inventory yet, so until now the
 * only thing a line could hold was whatever the packing slip called it. But the
 * box is usually right there while the manifest is being typed, and the two
 * facts worth capturing at that moment — what its code is, and what it looks
 * like — had nowhere to go. Both had to be recorded again later, from memory,
 * against a name off a slip.
 *
 * They live on the line rather than on a product because the product does not
 * exist yet, and inventing one at staging is the thing staging exists to avoid.
 * When the pallet lands and the line becomes a product, both move across.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pallet_lines', function (Blueprint $table) {
            $table->string('barcode')->nullable()->after('description');
            $table->string('photo_path')->nullable()->after('barcode');
        });
    }

    public function down(): void
    {
        Schema::table('pallet_lines', function (Blueprint $table) {
            $table->dropColumn(['barcode', 'photo_path']);
        });
    }
};
