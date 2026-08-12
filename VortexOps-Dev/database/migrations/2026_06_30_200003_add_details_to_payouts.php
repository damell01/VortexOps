<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payouts', function (Blueprint $table) {
            $table->foreignId('whatnot_channel_id')->nullable()->after('streamer_id')
                  ->constrained()->nullOnDelete();
            $table->unsignedInteger('pwe_count')->nullable()->after('tips_included');
            $table->unsignedInteger('label_count')->nullable()->after('pwe_count');
            $table->decimal('burden_rate_applied', 10, 4)->nullable()->after('label_count');
            $table->string('routing_bank_label')->nullable()->after('burden_rate_applied');
        });
    }

    public function down(): void
    {
        Schema::table('payouts', function (Blueprint $table) {
            $table->dropForeign(['whatnot_channel_id']);
            $table->dropColumn(['whatnot_channel_id', 'pwe_count', 'label_count', 'burden_rate_applied', 'routing_bank_label']);
        });
    }
};
