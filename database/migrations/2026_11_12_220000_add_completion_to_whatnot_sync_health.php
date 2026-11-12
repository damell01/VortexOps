<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('whatnot_sync_health', function (Blueprint $table) {
            $table->timestamp('last_complete_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('whatnot_sync_health', function (Blueprint $table) {
            $table->dropColumn('last_complete_at');
        });
    }
};
