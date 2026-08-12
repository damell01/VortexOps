<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('shows', 'detail_url')) {
            return;
        }

        Schema::table('shows', function (Blueprint $table) {
            $table->string('detail_url')->nullable()->after('import_source');
        });
    }

    public function down(): void
    {
        Schema::table('shows', function (Blueprint $table) {
            $table->dropColumn('detail_url');
        });
    }
};
