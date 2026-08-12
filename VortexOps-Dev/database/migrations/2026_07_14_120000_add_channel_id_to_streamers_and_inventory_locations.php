<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('streamers', function (Blueprint $table) {
            $table->foreignId('whatnot_channel_id')->nullable()->after('user_id')
                ->constrained('whatnot_channels')->nullOnDelete();
        });

        Schema::table('inventory_locations', function (Blueprint $table) {
            $table->foreignId('whatnot_channel_id')->nullable()->after('streamer_id')
                ->constrained('whatnot_channels')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('streamers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('whatnot_channel_id');
        });

        Schema::table('inventory_locations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('whatnot_channel_id');
        });
    }
};
