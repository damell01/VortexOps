<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('pallets', 'receiving_session_id')) {
            return;
        }

        Schema::table('pallets', function (Blueprint $table) {
            $table->foreignId('receiving_session_id')->nullable()->after('id')
                ->constrained('receiving_sessions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pallets', function (Blueprint $table) {
            $table->dropForeign(['receiving_session_id']);
            $table->dropColumn('receiving_session_id');
        });
    }
};
