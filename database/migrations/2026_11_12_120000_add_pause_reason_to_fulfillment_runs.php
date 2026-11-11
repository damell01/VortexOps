<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('fulfillment_runs', 'pause_reason')) return;
        Schema::table('fulfillment_runs', function (Blueprint $table) {
            $table->string('pause_reason', 32)->nullable()->after('paused_at');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('fulfillment_runs', 'pause_reason')) {
            Schema::table('fulfillment_runs', fn (Blueprint $table) => $table->dropColumn('pause_reason'));
        }
    }
};
