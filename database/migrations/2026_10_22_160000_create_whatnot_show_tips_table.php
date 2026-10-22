<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('whatnot_show_tips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('show_id')->constrained('shows')->cascadeOnDelete();
            $table->foreignId('whatnot_channel_id')->constrained('whatnot_channels');
            $table->string('whatnot_tip_id', 160)->unique();
            $table->string('whatnot_livestream_id', 64);
            $table->unsignedBigInteger('amount_cents');
            $table->string('currency', 8)->default('USD');
            $table->timestamp('tipped_at')->nullable();
            $table->timestamps();
            $table->index(['show_id', 'tipped_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatnot_show_tips');
    }
};
