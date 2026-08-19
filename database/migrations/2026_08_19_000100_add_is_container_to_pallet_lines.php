<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether a staged line is a case or a single, recorded before anything is
 * mapped.
 *
 * A pallet is staged from the paperwork, often for items that do not exist in
 * inventory yet, and "case of 12" versus "one box" is the thing most worth
 * capturing at that moment — it decides the unit count and, when the line is
 * later turned into a product, whether that product is a container. Nullable
 * because it is optional at staging: unknown is a real answer, and is not the
 * same as "not a case".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pallet_lines', function (Blueprint $table) {
            $table->boolean('is_container')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('pallet_lines', function (Blueprint $table) {
            $table->dropColumn('is_container');
        });
    }
};
