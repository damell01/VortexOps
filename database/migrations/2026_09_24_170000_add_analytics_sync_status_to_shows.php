<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shows', function (Blueprint $table): void {
            $table->string('analytics_sync_status', 24)->nullable()->after('last_analytics_synced_at')->index();
            $table->string('analytics_sync_note', 255)->nullable()->after('analytics_sync_status');
            $table->timestamp('analytics_unavailable_at')->nullable()->after('analytics_sync_note')->index();
        });
    }

    public function down(): void
    {
        Schema::table('shows', function (Blueprint $table): void {
            $table->dropIndex(['analytics_sync_status']);
            $table->dropIndex(['analytics_unavailable_at']);
            $table->dropColumn(['analytics_sync_status', 'analytics_sync_note', 'analytics_unavailable_at']);
        });
    }
};
