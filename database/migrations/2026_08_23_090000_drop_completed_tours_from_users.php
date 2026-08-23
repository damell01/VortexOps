<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The guided tours are gone, and so is the column that remembered them.
 *
 * The earlier migration is left in place rather than edited: it already ran on
 * production, so rewriting it would only mean the column stayed there forever
 * on the one database that matters.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'completed_tours')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('completed_tours');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'completed_tours')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->json('completed_tours')->nullable();
        });
    }
};
