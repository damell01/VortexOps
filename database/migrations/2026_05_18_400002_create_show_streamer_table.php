<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('show_streamer', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatnot_show_id')->constrained('whatnot_shows')->cascadeOnDelete();
            $table->foreignId('streamer_id')->constrained('streamers')->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->unique(['whatnot_show_id', 'streamer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('show_streamer');
    }
};
