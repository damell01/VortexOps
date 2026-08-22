<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('streamer_log_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('streamer_log_items', 'disposition')) {
                $table->string('disposition', 32)->default('sold')->after('quantity')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('streamer_log_items', function (Blueprint $table): void {
            if (Schema::hasColumn('streamer_log_items', 'disposition')) {
                $table->dropColumn('disposition');
            }
        });
    }
};
