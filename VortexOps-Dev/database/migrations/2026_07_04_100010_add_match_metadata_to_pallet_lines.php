<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pallet_lines', function (Blueprint $table) {
            if (! Schema::hasColumn('pallet_lines', 'match_reasons'))
                $table->json('match_reasons')->nullable()->after('match_stage');
            if (! Schema::hasColumn('pallet_lines', 'matched_at'))
                $table->timestamp('matched_at')->nullable()->after('match_reasons');
            if (! Schema::hasColumn('pallet_lines', 'matched_by'))
                $table->foreignId('matched_by')->nullable()->after('matched_at')
                    ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pallet_lines', function (Blueprint $table) {
            $table->dropForeign(['matched_by']);
            $table->dropColumn(['match_reasons', 'matched_at', 'matched_by']);
        });
    }
};
