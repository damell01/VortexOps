<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deduction_request_lines', function (Blueprint $table) {
            $table->string('fulfillment_status', 32)->nullable()->after('ops_overridden');
            $table->text('fulfillment_note')->nullable()->after('fulfillment_status');
            $table->foreignId('fulfilled_by')->nullable()->after('fulfillment_note')->constrained('users')->nullOnDelete();
            $table->timestamp('fulfilled_at')->nullable()->after('fulfilled_by');
            $table->index(['deduction_request_id', 'fulfillment_status'], 'deduction_lines_fulfillment_idx');
        });
    }

    public function down(): void
    {
        Schema::table('deduction_request_lines', function (Blueprint $table) {
            $table->dropIndex('deduction_lines_fulfillment_idx');
            $table->dropConstrainedForeignId('fulfilled_by');
            $table->dropColumn(['fulfillment_status', 'fulfillment_note', 'fulfilled_at']);
        });
    }
};
