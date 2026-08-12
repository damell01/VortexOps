<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        try { Schema::table('pallet_lines', fn (Blueprint $t) => $t->dropUnique('pallet_lines_pallet_id_line_number_unique')); } catch (\Throwable) {}
        Schema::table('pallet_lines', fn (Blueprint $t) => $t->unique(['pallet_id', 'line_number'], 'pallet_lines_pallet_id_line_number_unique'));
    }

    public function down(): void
    {
        try { Schema::table('pallet_lines', fn (Blueprint $t) => $t->dropUnique('pallet_lines_pallet_id_line_number_unique')); } catch (\Throwable) {}
    }
};
