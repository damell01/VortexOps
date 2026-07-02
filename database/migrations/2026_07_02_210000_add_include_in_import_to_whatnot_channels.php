<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatnot_channels', function (Blueprint $table) {
            $table->boolean('include_in_import')->default(true)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('whatnot_channels', function (Blueprint $table) {
            $table->dropColumn('include_in_import');
        });
    }
};
