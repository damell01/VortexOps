<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_items', fn (Blueprint $t) => $t->softDeletes());
        Schema::table('pallets', fn (Blueprint $t) => $t->softDeletes());
        Schema::table('vendors', fn (Blueprint $t) => $t->softDeletes());
    }

    public function down(): void
    {
        Schema::table('inventory_items', fn (Blueprint $t) => $t->dropSoftDeletes());
        Schema::table('pallets', fn (Blueprint $t) => $t->dropSoftDeletes());
        Schema::table('vendors', fn (Blueprint $t) => $t->dropSoftDeletes());
    }
};
