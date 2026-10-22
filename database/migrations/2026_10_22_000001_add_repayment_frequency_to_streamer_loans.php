<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('streamer_loans', function (Blueprint $table) {
            $table->string('repayment_frequency', 16)->default('weekly');
            $table->date('last_repayment_period')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('streamer_loans', function (Blueprint $table) {
            $table->dropColumn(['repayment_frequency', 'last_repayment_period']);
        });
    }
};
