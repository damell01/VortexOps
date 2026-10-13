<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hours Worked: clocked time can be linked to a show or given a reason,
 * people can correct their own times (logged and flagged), and admins review
 * what was flagged before it is paid. Show reports keep their own hours; the
 * review of a report's hours against the clock is stored on the report.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('time_entries', function (Blueprint $table) {
            $table->foreignId('show_id')->nullable()->after('user_id')->constrained('shows')->nullOnDelete();
            $table->string('reason', 30)->nullable()->after('show_id');
            $table->string('source', 20)->default('clock')->after('reason');
            $table->string('status', 20)->default('ok')->after('source');
            $table->json('flags')->nullable()->after('status');
            $table->unsignedInteger('approved_minutes')->nullable()->after('flags');
            $table->foreignId('approved_by')->nullable()->after('approved_minutes')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->text('review_note')->nullable()->after('approved_at');
            $table->timestamp('edited_at')->nullable()->after('review_note');
            $table->timestamp('reminder_sent_at')->nullable()->after('edited_at');
            $table->index('status');
        });

        Schema::create('time_entry_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('time_entry_id')->nullable()->constrained('time_entries')->nullOnDelete();
            $table->foreignId('streamer_log_entry_id')->nullable()->constrained('streamer_log_entries')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 30);
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();
        });

        Schema::table('streamer_log_entries', function (Blueprint $table) {
            $table->string('hours_review', 20)->nullable();
            $table->foreignId('hours_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('hours_reviewed_at')->nullable();
            $table->text('hours_review_note')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('streamer_log_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('hours_reviewed_by');
            $table->dropColumn(['hours_review', 'hours_reviewed_at', 'hours_review_note']);
        });
        Schema::dropIfExists('time_entry_changes');
        Schema::table('time_entries', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropConstrainedForeignId('show_id');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['reason', 'source', 'status', 'flags', 'approved_minutes', 'approved_at', 'review_note', 'edited_at', 'reminder_sent_at']);
        });
    }
};
