<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payment processing fees on a received pallet.
 *
 * Card surcharges, PayPal fees, wire charges — money paid to acquire the
 * stock that is not on the invoice line and not the carrier's shipping
 * charge. It belongs in landed cost for the same reason shipping does: leave
 * it out and every margin calculation downstream is optimistic by whatever
 * the processor took.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pallets', function (Blueprint $table) {
            $table->decimal('payment_fees', 10, 2)
                ->nullable()
                ->default(0)
                ->after('shipping_cost');
        });
    }

    public function down(): void
    {
        Schema::table('pallets', function (Blueprint $table) {
            $table->dropColumn('payment_fees');
        });
    }
};
