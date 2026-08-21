<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shows', function (Blueprint $table): void {
            $table->unique('whatnot_show_id', 'shows_whatnot_show_id_unique');
        });

        Schema::create('whatnot_show_aliases', function (Blueprint $table): void {
            $table->id();
            $table->uuid('duplicate_whatnot_show_id')->unique();
            $table->foreignId('canonical_show_id')->constrained('shows')->cascadeOnDelete();
            $table->string('reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatnot_show_aliases');

        Schema::table('shows', function (Blueprint $table): void {
            $table->dropUnique('shows_whatnot_show_id_unique');
        });
    }
};
