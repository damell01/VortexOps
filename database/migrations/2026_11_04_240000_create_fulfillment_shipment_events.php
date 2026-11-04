<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('fulfillment_shipment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('shipments')->cascadeOnDelete();
            $table->string('source', 32)->default('seller_api');
            $table->string('previous_status', 64)->nullable();
            $table->string('status', 64);
            $table->string('tracking_number')->nullable();
            $table->timestamp('observed_at');
            $table->timestamps();
            $table->index(['shipment_id', 'observed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fulfillment_shipment_events');
    }
};
