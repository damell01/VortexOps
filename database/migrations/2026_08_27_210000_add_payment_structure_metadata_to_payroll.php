<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('streamers', function (Blueprint $table) {
            // null = legacy behavior: all current row values remain effective.
            // [] = inherit the role Payment Structure entirely.
            $table->json('compensation_override_fields')->nullable()->after('member_type');
        });

        Schema::table('payouts', function (Blueprint $table) {
            // Snapshot the effective role defaults + individual overrides used
            // for this calculation so future settings edits cannot rewrite history.
            $table->json('compensation_snapshot')->nullable()->after('calculation_notes');
            $table->string('calculation_version', 80)->nullable()->after('compensation_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('payouts', function (Blueprint $table) {
            $table->dropColumn(['compensation_snapshot', 'calculation_version']);
        });

        Schema::table('streamers', function (Blueprint $table) {
            $table->dropColumn('compensation_override_fields');
        });
    }
};
