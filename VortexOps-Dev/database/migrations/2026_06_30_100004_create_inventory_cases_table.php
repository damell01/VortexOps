<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pallet_line_id')->constrained()->cascadeOnDelete();
            $table->string('barcode')->nullable();
            $table->string('status')->default('expected'); // expected, received, opened
            $table->decimal('quantity_received', 10, 2)->nullable();
            $table->timestamp('received_at')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('barcode');
            $table->index('pallet_line_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_cases');
    }
};
