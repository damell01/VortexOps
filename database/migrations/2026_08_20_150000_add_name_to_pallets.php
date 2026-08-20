<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Let a pallet be called what people actually call it.
 *
 * Until now a pallet was identified by its PO number, or by nothing — and a PO
 * number is what the vendor calls it, not what anyone in the room does. Nobody
 * says "go and check PO-100", they say "the Topps Chrome one" or "pallet 4".
 * With four shipments open at once, a list of reference codes is a list you
 * have to open one at a time to tell apart.
 *
 * Separate from `reference` on purpose: the PO is the vendor's identifier and
 * has to keep matching their paperwork, so overloading it with a human name
 * would make one field answer to two masters. Nullable, because a pallet that
 * arrives with only a PO is still a pallet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pallets', function (Blueprint $table) {
            $table->string('name')->nullable()->after('vendor_id');
        });
    }

    public function down(): void
    {
        Schema::table('pallets', function (Blueprint $table) {
            $table->dropColumn('name');
        });
    }
};
