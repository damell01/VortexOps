<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pallets', function (Blueprint $table) {
            $table->string('carrier')->nullable()->after('status');
            $table->string('tracking_number')->nullable()->after('carrier');
            $table->date('expected_delivery_date')->nullable()->after('tracking_number');
            $table->timestamp('shipped_at')->nullable()->after('expected_delivery_date');
        });
    }

    public function down(): void
    {
        Schema::table('pallets', function (Blueprint $table) {
            $table->dropColumn(['carrier', 'tracking_number', 'expected_delivery_date', 'shipped_at']);
        });
    }
};
