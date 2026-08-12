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
        Schema::create('fulfillment_packages', function (Blueprint $table) {
            $table->id();
            $table->string('tracking_number')->unique();
            $table->string('carrier')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->dateTime('shipped_at')->nullable();
            $table->string('status')->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fulfillment_packages');
    }
};
