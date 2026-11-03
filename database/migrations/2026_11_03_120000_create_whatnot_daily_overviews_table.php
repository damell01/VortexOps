<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per channel per day from the Seller API analytics_overview_report
 * (developers.whatnot.com/reports): sales, earnings, orders, buyers,
 * followers, stream minutes, viewers per hour and watch time.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('whatnot_daily_overviews')) {
            return;
        }
        Schema::create('whatnot_daily_overviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatnot_channel_id')->constrained('whatnot_channels')->cascadeOnDelete();
            $table->date('date');
            $table->string('currency', 3)->nullable();
            foreach (['estimated_sales', 'in_show_estimated_sales', 'marketplace_estimated_sales',
                'estimated_earnings', 'in_show_estimated_earnings', 'marketplace_estimated_earnings', 'average_order_value'] as $money) {
                $table->decimal($money, 12, 2)->nullable();
            }
            foreach (['orders_count', 'in_show_orders_count', 'marketplace_orders_count', 'giveaway_orders_count',
                'buyers_count', 'new_buyers_count', 'returning_buyers_count', 'follows_count', 'referral_follows_count',
                'non_referral_follows_count', 'livestreams_count', 'stream_minutes', 'viewers_per_hour', 'average_watch_time_seconds'] as $count) {
                $table->integer($count)->nullable();
            }
            $table->timestamps();
            $table->unique(['whatnot_channel_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatnot_daily_overviews');
    }
};
