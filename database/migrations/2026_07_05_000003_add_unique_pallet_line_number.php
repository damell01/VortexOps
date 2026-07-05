<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pallet_lines', function (Blueprint $table) {
            $table->unique(['pallet_id', 'line_number'], 'pallet_lines_pallet_id_line_number_unique');
        });
    }

    public function down(): void
    {
        Schema::table('pallet_lines', function (Blueprint $table) {
            $table->dropUnique('pallet_lines_pallet_id_line_number_unique');
        });
    }
};
