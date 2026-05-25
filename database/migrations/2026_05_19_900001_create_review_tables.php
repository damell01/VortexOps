<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('status')->default('open'); // open, submitted, closed
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('review_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_session_id')->constrained()->cascadeOnDelete();
            $table->string('page_url');
            $table->string('page_title')->nullable();
            $table->longText('screenshot')->nullable();
            $table->longText('fabric_json')->nullable();
            $table->text('comment')->nullable();
            $table->string('type')->default('annotation'); // annotation, bug, suggestion, question
            $table->string('status')->default('open');     // open, in_progress, resolved, rejected
            $table->string('priority')->default('normal'); // low, normal, high
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('review_item_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_item_comments');
        Schema::dropIfExists('review_items');
        Schema::dropIfExists('review_sessions');
    }
};
