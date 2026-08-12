<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop old complex project-hub / review / AI tables that are no longer used
        Schema::dropIfExists('review_item_comments');
        Schema::dropIfExists('review_items');
        Schema::dropIfExists('review_sessions');
        Schema::dropIfExists('ai_logs');
        Schema::dropIfExists('project_approvals');
        Schema::dropIfExists('project_status_updates');
        Schema::dropIfExists('project_comments');
        Schema::dropIfExists('project_milestones');
        Schema::dropIfExists('projects');

        // Lightweight project tracker
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status', 32)->default('planning');
            $table->string('priority', 16)->default('medium');
            $table->string('color', 7)->default('#6366f1');
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('target_date')->nullable();
            $table->timestamps();
        });

        Schema::create('project_milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('due_date')->nullable();
            $table->string('status', 16)->default('pending');
            $table->timestamp('completed_at')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index('project_id');
        });

        Schema::create('project_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->string('type', 16)->default('update');
            $table->boolean('is_resolved')->default(false);
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index('project_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_updates');
        Schema::dropIfExists('project_milestones');
        Schema::dropIfExists('projects');
    }
};
