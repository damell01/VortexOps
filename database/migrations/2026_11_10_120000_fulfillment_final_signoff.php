<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Final sign-off: the fulfillment admin checks off every package (or buyer,
 * for shows packed without shipment records) and signs the show off with
 * their typed name. Who checked what, when, and the signature stay on the
 * record.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fulfillment_runs', function (Blueprint $table) {
            if (! Schema::hasColumn('fulfillment_runs', 'signoff_signature')) $table->string('signoff_signature', 120)->nullable();
        });
        foreach (['fulfillment_run_packages', 'fulfillment_run_buyers'] as $name) {
            Schema::table($name, function (Blueprint $table) use ($name) {
                if (! Schema::hasColumn($name, 'checked_at')) $table->timestamp('checked_at')->nullable();
                if (! Schema::hasColumn($name, 'checked_by')) $table->unsignedBigInteger('checked_by')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::table('fulfillment_runs', function (Blueprint $table) {
            if (Schema::hasColumn('fulfillment_runs', 'signoff_signature')) $table->dropColumn('signoff_signature');
        });
        foreach (['fulfillment_run_packages', 'fulfillment_run_buyers'] as $name) {
            Schema::table($name, function (Blueprint $table) use ($name) {
                foreach (['checked_at', 'checked_by'] as $c) if (Schema::hasColumn($name, $c)) $table->dropColumn($c);
            });
        }
    }
};
