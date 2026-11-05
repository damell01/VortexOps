<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('fulfillment_print_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('show_id')->constrained('shows');
            $table->foreignId('created_by')->constrained('users');
            $table->json('shipment_ids');
            $table->unsignedInteger('document_count');
            $table->string('status', 24)->default('prepared');
            $table->timestamp('prepared_at');
            $table->timestamps();
            $table->index(['show_id', 'prepared_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fulfillment_print_jobs');
    }
};
