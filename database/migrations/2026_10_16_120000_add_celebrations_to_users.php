<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** How much celebrating each person wants: all, milestones (the big ones only) or off. */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('celebrations', 16)->default('all')->after('notification_preferences');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('celebrations');
        });
    }
};
