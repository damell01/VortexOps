<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What kind of break this was.
 *
 * Every show was recorded identically, so the numbers averaged across formats
 * that behave nothing alike — a sudden death and a giveaway-heavy night land
 * in the same mean, and the mean describes neither. Knowing that one format
 * turns over more per hour than another is only answerable if the shows say
 * which they were.
 *
 * Deliberately nullable and set after the fact: nobody knows how a night went
 * while they are running it, and forcing a guess up front would fill the
 * column with defaults nobody meant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shows', function (Blueprint $table) {
            $table->string('show_format', 40)->nullable()->after('is_slow_pack');

            // Every question asked of this column is "these shows, by date".
            $table->index(['show_format', 'show_date']);
        });
    }

    public function down(): void
    {
        Schema::table('shows', function (Blueprint $table) {
            $table->dropIndex(['show_format', 'show_date']);
            $table->dropColumn('show_format');
        });
    }
};
