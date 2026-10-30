<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('whatnot_api_sync_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatnot_channel_id')->constrained('whatnot_channels')->cascadeOnDelete();
            $table->string('resource', 40);
            $table->string('source', 30)->default('api');
            $table->string('status', 30)->default('pending');
            $table->unsignedInteger('received_count')->default(0);
            $table->unsignedInteger('matched_count')->default(0);
            $table->unsignedInteger('unmatched_count')->default(0);
            $table->json('summary')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['whatnot_channel_id', 'resource', 'created_at'], 'whatnot_api_audit_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatnot_api_sync_audits');
    }
};
