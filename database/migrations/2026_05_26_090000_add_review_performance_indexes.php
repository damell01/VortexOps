<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('review_sessions', function (Blueprint $table) {
            $table->index('status');
            $table->index('created_by');
        });

        Schema::table('review_items', function (Blueprint $table) {
            $table->index('review_session_id');
            $table->index('status');
            $table->index('created_by');
            $table->index('assigned_to');
            $table->index('priority');
            $table->index('type');
            $table->index(['review_session_id', 'status']);
            $table->index(['created_by', 'status']);
        });

        Schema::table('review_item_comments', function (Blueprint $table) {
            $table->index('review_item_id');
            $table->index('user_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('review_item_comments', function (Blueprint $table) {
            $table->dropIndex(['review_item_id']);
            $table->dropIndex(['user_id']);
            $table->dropIndex(['created_at']);
        });

        Schema::table('review_items', function (Blueprint $table) {
            $table->dropIndex(['review_session_id']);
            $table->dropIndex(['status']);
            $table->dropIndex(['created_by']);
            $table->dropIndex(['assigned_to']);
            $table->dropIndex(['priority']);
            $table->dropIndex(['type']);
            $table->dropIndex(['review_session_id', 'status']);
            $table->dropIndex(['created_by', 'status']);
        });

        Schema::table('review_sessions', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['created_by']);
        });
    }
};
