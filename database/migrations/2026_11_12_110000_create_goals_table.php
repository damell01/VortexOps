<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('goals')) return;
        Schema::create('goals', function (Blueprint $table) {
            $table->id();
            $table->date('month');                       // first day of the month
            $table->string('scope', 16)->default('all'); // all | business | channel
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->string('metric', 32);                // whatnot_net | gross | shows | new_buyers
            $table->decimal('target', 12, 2);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['month', 'scope', 'scope_id', 'metric']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goals');
    }
};
