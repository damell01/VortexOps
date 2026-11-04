<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('fulfillment_runs', function (Blueprint $table) {
            $table->unsignedInteger('reported_shipments')->nullable();
            $table->unsignedInteger('reported_pwes')->nullable();
            $table->boolean('hard_copy_verified')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('verification_notes')->nullable();
            $table->timestamp('payroll_handoff_at')->nullable();
            $table->foreignId('payroll_handoff_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('payroll_split_method', 24)->nullable();
            $table->text('payroll_split_notes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('fulfillment_runs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('verified_by');
            $table->dropConstrainedForeignId('payroll_handoff_by');
            $table->dropColumn(['reported_shipments', 'reported_pwes', 'hard_copy_verified',
                'verified_at', 'verification_notes', 'payroll_handoff_at',
                'payroll_split_method', 'payroll_split_notes']);
        });
    }
};
