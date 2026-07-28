<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_value_snapshots', function (Blueprint $table) {
            $table->id();
            $table->date('snapshot_date');
            // Nullable = the all-channels combined snapshot, alongside one row
            // per channel — matches how ChannelContext scoping works elsewhere.
            $table->foreignId('whatnot_channel_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('total_value', 14, 2)->default(0);
            $table->decimal('total_quantity', 14, 2)->default(0);
            $table->unsignedInteger('total_items')->default(0);
            $table->timestamps();

            $table->unique(['snapshot_date', 'whatnot_channel_id']);
            $table->index('snapshot_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_value_snapshots');
    }
};
