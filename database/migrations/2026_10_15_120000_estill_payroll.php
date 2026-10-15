<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Estill Distribution's payroll:
 *  - shows.house_cut_override: on a special show, the % the business keeps
 *    from a sales-split streamer instead of their usual one.
 *  - businesses.tips_monthly: tips come off the weekly pay onto their own
 *    line, paid by a monthly Tips pay run.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shows', function (Blueprint $table) {
            $table->decimal('house_cut_override', 5, 2)->nullable();
        });
        Schema::table('businesses', function (Blueprint $table) {
            $table->boolean('tips_monthly')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('shows', fn (Blueprint $table) => $table->dropColumn('house_cut_override'));
        Schema::table('businesses', fn (Blueprint $table) => $table->dropColumn('tips_monthly'));
    }
};
