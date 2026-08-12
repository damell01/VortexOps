<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deduction_request_lines', function (Blueprint $table) {
            $table->string('match_stage')->nullable()->after('ai_reason');
        });
    }

    public function down(): void
    {
        Schema::table('deduction_request_lines', function (Blueprint $table) {
            $table->dropColumn('match_stage');
        });
    }
};
