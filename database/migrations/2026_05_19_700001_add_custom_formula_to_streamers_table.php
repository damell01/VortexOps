<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('streamers', function (Blueprint $table) {
            $table->text('custom_payout_formula')->nullable()->after('hourly_rate');
        });
    }

    public function down(): void
    {
        Schema::table('streamers', function (Blueprint $table) {
            $table->dropColumn('custom_payout_formula');
        });
    }
};
