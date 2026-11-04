<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fulfillment_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')->constrained('fulfillment_runs')->cascadeOnDelete();
            $table->foreignId('buyer_id')->constrained('fulfillment_run_buyers')->cascadeOnDelete();
            $table->foreignId('verified_by')->constrained('users');
            $table->string('barcode', 128);
            $table->string('result', 24);
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamps();
            $table->index(['run_id', 'buyer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fulfillment_verifications');
    }
};
