<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shows', function (Blueprint $table) {
            $table->string('whatnot_show_id')->nullable()->unique()->after('whatnot_channel_id')
                ->comment('Whatnot internal show ID — used for sync deduplication');
            $table->string('cover_image_url')->nullable()->after('whatnot_show_id');
            $table->decimal('whatnot_fees', 10, 2)->nullable()->after('whatnot_net');
            $table->decimal('whatnot_payout_amount', 10, 2)->nullable()->after('whatnot_fees');
            $table->timestamp('last_synced_at')->nullable()->after('whatnot_payout_amount');
        });

        Schema::table('shows', function (Blueprint $table) {
            $table->index('last_synced_at');
        });
    }

    public function down(): void
    {
        Schema::table('shows', function (Blueprint $table) {
            $table->dropIndex(['last_synced_at']);
            $table->dropColumn(['whatnot_show_id', 'cover_image_url', 'whatnot_fees', 'whatnot_payout_amount', 'last_synced_at']);
        });
    }
};
