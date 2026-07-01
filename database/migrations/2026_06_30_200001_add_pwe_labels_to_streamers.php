<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('streamers', function (Blueprint $table) {
            // PWE + Labels payout fields
            $table->decimal('pwe_rate', 10, 4)->nullable()->after('custom_payout_formula');
            $table->decimal('label_rate', 10, 4)->nullable()->after('pwe_rate');

            // Burden rate (applied to base pay before tips/profit share in hybrid model)
            $table->string('burden_rate_type')->nullable()->after('label_rate'); // percentage or flat
            $table->decimal('burden_rate_value', 10, 4)->nullable()->after('burden_rate_type');

            // Running balance totals
            $table->decimal('total_earnings_due', 14, 2)->default(0)->after('burden_rate_value');
            $table->decimal('total_earnings_paid', 14, 2)->default(0)->after('total_earnings_due');

            // Per-channel payout routing rules (JSON array of routing rule objects)
            $table->json('channel_routing_rules')->nullable()->after('total_earnings_paid');
        });
    }

    public function down(): void
    {
        Schema::table('streamers', function (Blueprint $table) {
            $table->dropColumn([
                'pwe_rate', 'label_rate',
                'burden_rate_type', 'burden_rate_value',
                'total_earnings_due', 'total_earnings_paid',
                'channel_routing_rules',
            ]);
        });
    }
};
