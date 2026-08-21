<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shows', function (Blueprint $table): void {
            $table->timestamp('last_analytics_synced_at')->nullable()->after('last_synced_at')->index();
            $table->timestamp('last_shipments_synced_at')->nullable()->after('last_analytics_synced_at')->index();
            $table->timestamp('last_orders_synced_at')->nullable()->after('last_shipments_synced_at')->index();
        });
    }

    public function down(): void
    {
        Schema::table('shows', function (Blueprint $table): void {
            $table->dropColumn([
                'last_analytics_synced_at',
                'last_shipments_synced_at',
                'last_orders_synced_at',
            ]);
        });
    }
};
