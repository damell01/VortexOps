<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatnot_channels', function (Blueprint $table) {
            if (! Schema::hasColumn('whatnot_channels', 'logo_path')) {
                $table->string('logo_path')->nullable()->after('status');
            }
            if (! Schema::hasColumn('whatnot_channels', 'display_title')) {
                $table->string('display_title')->nullable()->after('logo_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('whatnot_channels', function (Blueprint $table) {
            $table->dropColumn(['logo_path', 'display_title']);
        });
    }
};
