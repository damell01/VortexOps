<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('streamers', function (Blueprint $table) {
            $table->string('streamer_type', 20)->default('in_house')->after('member_type')->index();
            $table->date('pay_rate_effective_at')->nullable()->after('hourly_rate');
        });

        Schema::create('streamer_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('streamer_id')->constrained()->cascadeOnDelete();
            $table->string('alias', 255);
            $table->string('normalized_alias', 255)->index();
            $table->string('source', 40)->default('manual');
            $table->timestamps();
            $table->unique(['streamer_id', 'normalized_alias']);
        });

        Schema::table('shows', function (Blueprint $table) {
            $table->index(['is_operational', 'show_date'], 'shows_operational_date_idx');
            $table->index(['is_operational', 'status', 'show_date'], 'shows_operational_status_date_idx');
            $table->index(['is_operational', 'analytics_sync_status', 'show_date'], 'shows_operational_analytics_date_idx');
        });
    }

    public function down(): void
    {
        Schema::table('shows', function (Blueprint $table) {
            $table->dropIndex('shows_operational_date_idx');
            $table->dropIndex('shows_operational_status_date_idx');
            $table->dropIndex('shows_operational_analytics_date_idx');
        });
        Schema::dropIfExists('streamer_aliases');
        Schema::table('streamers', function (Blueprint $table) {
            $table->dropIndex(['streamer_type']);
            $table->dropColumn(['streamer_type', 'pay_rate_effective_at']);
        });
    }
};
