<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shows', function (Blueprint $table) {
            // Sales Metrics (from Whatnot analytics dashboard)
            $table->decimal('completed_earnings', 10, 2)->nullable()->after('whatnot_net');
            $table->decimal('avg_order_value', 8, 2)->nullable()->after('completed_earnings');
            $table->decimal('giveaway_spend', 8, 2)->nullable()->after('avg_order_value');
            $table->unsignedSmallInteger('giveaways_count')->nullable()->after('giveaway_spend');

            // Stream Metrics
            $table->unsignedSmallInteger('buyers_count')->nullable()->after('giveaways_count');
            $table->unsignedSmallInteger('first_time_buyers')->nullable()->after('buyers_count');
            $table->unsignedSmallInteger('returning_buyers')->nullable()->after('first_time_buyers');
            $table->unsignedSmallInteger('shares_count')->nullable()->after('returning_buyers');
            $table->unsignedSmallInteger('max_concurrent_viewers')->nullable()->after('shares_count');
            $table->unsignedInteger('total_views')->nullable()->after('max_concurrent_viewers');
            $table->decimal('avg_order_rating', 3, 2)->nullable()->after('total_views');
        });
    }

    public function down(): void
    {
        Schema::table('shows', function (Blueprint $table) {
            $table->dropColumn([
                'completed_earnings', 'avg_order_value', 'giveaway_spend', 'giveaways_count',
                'buyers_count', 'first_time_buyers', 'returning_buyers', 'shares_count',
                'max_concurrent_viewers', 'total_views', 'avg_order_rating',
            ]);
        });
    }
};
