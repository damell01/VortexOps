<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The inputs a profit-share payout was worked out from.
 *
 * A payout stored its amount and a sentence of notes, so checking one against
 * the calculations sheet meant opening the show report and re-doing the
 * arithmetic by hand. These are the four numbers the formula reads — the same
 * ones the sheet's own columns hold — kept beside the answer so a payout can
 * show its working.
 *
 * Null on payouts that are not profit-based: a package rate has no net revenue
 * behind it, and storing zeros there would read as "we worked it out and got
 * nothing" rather than "this does not apply".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payouts', function (Blueprint $table) {
            $table->decimal('product_cost', 12, 2)->nullable()->after('gross_show_revenue');
            $table->decimal('hours_worked', 8, 2)->nullable()->after('product_cost');
            $table->unsignedInteger('shipments_count')->nullable()->after('hours_worked');
            $table->decimal('burden_amount', 12, 2)->nullable()->after('shipments_count');
            $table->decimal('net_revenue_basis', 12, 2)->nullable()->after('burden_amount');
        });
    }

    public function down(): void
    {
        Schema::table('payouts', function (Blueprint $table) {
            $table->dropColumn([
                'product_cost',
                'hours_worked',
                'shipments_count',
                'burden_amount',
                'net_revenue_basis',
            ]);
        });
    }
};
