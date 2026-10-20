<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_conversations', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 12);
            $table->string('title', 120)->nullable();
            $table->string('direct_key', 80)->nullable()->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_preview')->default(true);
            $table->timestamp('last_message_at')->nullable()->index();
            $table->timestamps();
        });
        Schema::create('team_conversation_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('team_conversations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('last_read_message_id')->nullable();
            $table->timestamps();
            $table->unique(['conversation_id', 'user_id']);
        });
        Schema::create('team_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('team_conversations')->cascadeOnDelete();
            $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('client_key');
            $table->text('body');
            $table->timestamps();
            $table->unique(['conversation_id', 'client_key']);
            $table->index(['conversation_id', 'id']);
        });
        Schema::create('team_announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 160);
            $table->text('body');
            $table->string('tone', 12)->default('info');
            $table->string('audience', 12)->default('all');
            $table->json('role_ids')->nullable();
            $table->json('user_ids')->nullable();
            $table->boolean('show_banner')->default(true);
            $table->boolean('email_requested')->default(false);
            $table->boolean('push_requested')->default(false);
            $table->boolean('is_preview')->default(true);
            $table->string('status', 12)->default('draft');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->index(['is_preview', 'status', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_announcements');
        Schema::dropIfExists('team_messages');
        Schema::dropIfExists('team_conversation_members');
        Schema::dropIfExists('team_conversations');
    }
};
