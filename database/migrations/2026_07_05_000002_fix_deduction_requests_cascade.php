<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deduction_requests', function (Blueprint $table) {
            $table->dropForeign(['show_id']);
            $table->foreign('show_id')
                ->references('id')->on('shows')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('deduction_requests', function (Blueprint $table) {
            $table->dropForeign(['show_id']);
            $table->foreign('show_id')
                ->references('id')->on('shows')
                ->cascadeOnDelete();
        });
    }
};
