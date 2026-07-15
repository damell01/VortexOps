<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            // Typical days from placing an order with this vendor to receiving it.
            // Feeds the reorder-quantity suggestion on Product Insights; null falls
            // back to ProductInsights::DEFAULT_LEAD_TIME_DAYS.
            if (! Schema::hasColumn('vendors', 'lead_time_days')) {
                $table->unsignedSmallInteger('lead_time_days')->nullable()->after('account_number');
            }
        });
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            if (Schema::hasColumn('vendors', 'lead_time_days')) {
                $table->dropColumn('lead_time_days');
            }
        });
    }
};
