<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('streamers', function (Blueprint $table) {
            $table->string('owner_fee_type')->nullable()->after('adp_employee_id');   // 'percentage' | 'flat'
            $table->decimal('owner_fee_value', 10, 2)->nullable()->after('owner_fee_type');
            $table->boolean('owner_fee_deduct_from_payout')->default(false)->after('owner_fee_value');
        });
    }

    public function down(): void
    {
        Schema::table('streamers', function (Blueprint $table) {
            $table->dropColumn(['owner_fee_type', 'owner_fee_value', 'owner_fee_deduct_from_payout']);
        });
    }
};
