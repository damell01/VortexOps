<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_locations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['main_storage', 'streamer_inventory', 'returned', 'damaged', 'fulfillment', 'other'])->default('other');
            $table->foreignId('streamer_id')->nullable()->constrained('streamers')->nullOnDelete();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_locations');
    }
};
