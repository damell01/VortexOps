<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_cases', function (Blueprint $table) {
            // Drop the plain index first, then replace with unique (NULLs are allowed by SQL standard)
            $table->dropIndex('inventory_cases_barcode_index');
            $table->unique('barcode');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_cases', function (Blueprint $table) {
            $table->dropUnique(['barcode']);
            $table->index('barcode');
        });
    }
};
