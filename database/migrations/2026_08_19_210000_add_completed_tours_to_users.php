<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which guided tours a person has already been shown.
 *
 * Kept per user rather than in the browser: staff sign in from the packing
 * bench, a phone and a laptop, and a tour that reintroduces itself on every
 * device is the kind of thing people learn to dismiss without reading.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'completed_tours')) {
                $table->json('completed_tours')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('completed_tours');
        });
    }
};
