<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('shows', function (Blueprint $table) {
            $table->integer('shipping_surcharge_count')->default(0)->after('tips');
            $table->decimal('shipping_surcharge_total', 10, 2)->default(0)->after('shipping_surcharge_count');
        });
    }

    public function down(): void
    {
        Schema::table('shows', function (Blueprint $table) {
            $table->dropColumn(['shipping_surcharge_count', 'shipping_surcharge_total']);
        });
    }
};
