<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('streamer_loan_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('streamer_loan_id')->constrained('streamer_loans')->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->date('payment_date');
            $table->string('source')->default('manual');
            $table->text('note')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['streamer_loan_id', 'payment_date']);
        });
    }
    public function down(): void { Schema::dropIfExists('streamer_loan_payments'); }
};
