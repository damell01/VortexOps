<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An append-only log of every AI call — one row per gateway request — so
 * latency, model usage, and failure rates are observable over time. Deliberately
 * lean (created_at only) and prunable; it is telemetry, not domain data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_interactions', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 40)->index();
            $table->string('task', 20)->index();
            $table->string('model', 120);
            $table->unsignedInteger('latency_ms')->default(0);
            $table->boolean('success')->default(true)->index();
            $table->text('error')->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_interactions');
    }
};
