<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('shows', 'channel_attribution_suspect')) {
            Schema::table('shows', function (Blueprint $table) {
                // Set when a show scraped under one channel already belongs to a
                // different channel — a sign the Whatnot channel switch did not take
                // effect and the attribution may be wrong. Surfaced for review.
                $table->boolean('channel_attribution_suspect')->default(false)->after('whatnot_channel_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('shows', 'channel_attribution_suspect')) {
            Schema::table('shows', function (Blueprint $table) {
                $table->dropColumn('channel_attribution_suspect');
            });
        }
    }
};
