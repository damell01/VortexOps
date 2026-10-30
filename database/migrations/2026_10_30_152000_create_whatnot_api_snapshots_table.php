<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('whatnot_api_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatnot_channel_id')->constrained('whatnot_channels')->cascadeOnDelete();
            $table->string('resource', 32);
            $table->string('payload_sha256', 64);
            $table->json('payload');
            $table->timestamp('fetched_at');
            $table->timestamps();
            $table->unique(['whatnot_channel_id', 'resource', 'payload_sha256'], 'whatnot_api_snapshot_dedup');
            $table->index(['whatnot_channel_id', 'resource', 'fetched_at'], 'whatnot_api_snapshot_lookup');
        });
    }
    public function down(): void { Schema::dropIfExists('whatnot_api_snapshots'); }
};
