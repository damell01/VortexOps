<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('streamers', function (Blueprint $table) {
            $table->decimal('monthly_burden_addback', 10, 2)->nullable();
            $table->unsignedTinyInteger('monthly_burden_payments')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('streamers', function (Blueprint $table) {
            $table->dropColumn(['monthly_burden_addback', 'monthly_burden_payments']);
        });
    }
};
