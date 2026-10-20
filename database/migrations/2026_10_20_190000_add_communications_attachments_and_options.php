<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_message_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained('team_messages')->cascadeOnDelete();
            $table->string('path');
            $table->string('name');
            $table->string('mime', 120);
            $table->unsignedBigInteger('size');
            $table->timestamps();
        });
        Schema::table('team_announcements', function (Blueprint $table) {
            $table->boolean('in_app_requested')->default(true);
            $table->boolean('dismissible')->default(true);
        });
    }
    public function down(): void
    {
        Schema::table('team_announcements', fn (Blueprint $table) => $table->dropColumn(['in_app_requested', 'dismissible']));
        Schema::dropIfExists('team_message_attachments');
    }
};
