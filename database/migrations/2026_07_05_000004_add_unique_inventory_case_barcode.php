<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        try { Schema::table('inventory_cases', fn (Blueprint $t) => $t->dropIndex('inventory_cases_barcode_index')); } catch (\Throwable) {}
        try { Schema::table('inventory_cases', fn (Blueprint $t) => $t->dropUnique(['barcode'])); } catch (\Throwable) {}
        Schema::table('inventory_cases', fn (Blueprint $t) => $t->unique('barcode'));
    }

    public function down(): void
    {
        try { Schema::table('inventory_cases', fn (Blueprint $t) => $t->dropUnique(['barcode'])); } catch (\Throwable) {}
        Schema::table('inventory_cases', fn (Blueprint $t) => $t->index('barcode'));
    }
};
