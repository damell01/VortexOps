<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A pay run's type: null pays everything (every run so far), 'wages' pays
 * hourly and other non-share pay, 'share' pays profit share — so profit
 * share can be paid monthly while wages stay weekly. See PayRunSchedules.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('weekly_payout_batches', function (Blueprint $table) {
            $table->string('kind', 20)->nullable()->after('business_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('weekly_payout_batches', function (Blueprint $table) {
            $table->dropIndex(['kind']);
            $table->dropColumn('kind');
        });
    }
};
