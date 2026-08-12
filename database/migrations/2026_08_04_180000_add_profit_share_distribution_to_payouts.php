<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payouts', function (Blueprint $table) {
            $table->decimal('manager_share_percentage', 5, 2)->nullable()->default(null)->after('calculated_payout');
            $table->decimal('manager_share_amount', 12, 2)->nullable()->default(null)->after('manager_share_percentage');
            $table->decimal('streamer_share_amount', 12, 2)->nullable()->default(null)->after('manager_share_amount');
            $table->text('distribution_notes')->nullable()->after('streamer_share_amount');
            $table->enum('distribution_status', ['pending_approval', 'approved', 'distributed'])->nullable()->after('distribution_notes');
        });
    }

    public function down(): void
    {
        Schema::table('payouts', function (Blueprint $table) {
            $table->dropColumn([
                'manager_share_percentage',
                'manager_share_amount',
                'streamer_share_amount',
                'distribution_notes',
                'distribution_status',
            ]);
        });
    }
};
