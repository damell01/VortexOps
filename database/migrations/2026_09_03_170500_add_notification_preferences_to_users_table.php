<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('notifications_enabled')->default(true)->after('timezone');
            $table->boolean('notification_in_app_enabled')->default(true)->after('notifications_enabled');
            $table->boolean('notification_email_enabled')->default(true)->after('notification_in_app_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'notifications_enabled',
                'notification_in_app_enabled',
                'notification_email_enabled',
            ]);
        });
    }
};
