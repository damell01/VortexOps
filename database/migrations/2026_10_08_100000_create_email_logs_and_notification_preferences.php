<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Every email the app hands to the mailer, with exactly what it said.
        if (! Schema::hasTable('email_logs')) {
            Schema::create('email_logs', function (Blueprint $table) {
                $table->id();
                $table->string('event')->nullable()->index();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('to_email')->index();
                $table->string('to_name')->nullable();
                $table->string('subject')->nullable();
                $table->longText('html')->nullable();
                $table->text('text')->nullable();
                $table->string('status', 20)->default('sending')->index();
                $table->text('error')->nullable();
                $table->boolean('is_test')->default(false);
                $table->string('message_id')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();
                $table->index(['to_email', 'created_at']);
            });
        }

        // Per-event choices: {"low_stock": {"in_app": true, "email": false}}.
        if (! Schema::hasColumn('users', 'notification_preferences')) {
            Schema::table('users', function (Blueprint $table) {
                $table->json('notification_preferences')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('email_logs');

        if (Schema::hasColumn('users', 'notification_preferences')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('notification_preferences');
            });
        }
    }
};
