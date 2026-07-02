<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('time_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->dateTime('clocked_in_at');
            $table->dateTime('clocked_out_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'clocked_in_at']);
            $table->index('clocked_in_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('time_entries');
    }
};
