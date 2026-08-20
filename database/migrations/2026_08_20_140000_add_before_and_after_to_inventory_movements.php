<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Record what the quantity was and what it became.
 *
 * A movement stores `quantity` as an absolute value and encodes the direction
 * in which of from_location_id / to_location_id is set. That works for a
 * transfer, where both are real places, and it falls apart for an adjustment,
 * where the direction is the whole point: every surface that read `quantity`
 * on its own showed a reduction as a positive number, because the number is
 * positive. The scanner's movement list did exactly that — `qty > 0 ? '+' : ''`
 * against a column that is never negative, so every row said "+".
 *
 * Inferring it from the location columns instead would work, but it is still an
 * inference, and one that has to be repeated correctly in a dozen views. The
 * before and after are facts we hold at the moment of the write; storing them
 * makes the change arithmetic rather than interpretation, and makes the history
 * an audit trail — you can see what the quantity actually was, not only what
 * moved.
 *
 * Nullable because every existing row predates this. Those keep reading their
 * direction from the location columns; see InventoryMovement::signedChange().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->decimal('quantity_before', 12, 2)->nullable()->after('quantity');
            $table->decimal('quantity_after', 12, 2)->nullable()->after('quantity_before');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropColumn(['quantity_before', 'quantity_after']);
        });
    }
};
