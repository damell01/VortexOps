<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        try { Schema::table('deduction_requests', fn (Blueprint $t) => $t->dropForeign(['show_id'])); } catch (\Throwable) {}
        Schema::table('deduction_requests', function (Blueprint $table) {
            $table->foreign('show_id')
                ->references('id')->on('shows')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        try { Schema::table('deduction_requests', fn (Blueprint $t) => $t->dropForeign(['show_id'])); } catch (\Throwable) {}
        Schema::table('deduction_requests', function (Blueprint $table) {
            $table->foreign('show_id')
                ->references('id')->on('shows')
                ->cascadeOnDelete();
        });
    }
};
