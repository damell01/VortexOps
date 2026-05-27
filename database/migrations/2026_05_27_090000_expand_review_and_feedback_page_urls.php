<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('review_items', function (Blueprint $table) {
            $table->text('page_url')->change();
        });

        Schema::table('feedback_tickets', function (Blueprint $table) {
            $table->text('page_url')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('review_items', function (Blueprint $table) {
            $table->string('page_url')->change();
        });

        Schema::table('feedback_tickets', function (Blueprint $table) {
            $table->string('page_url', 500)->nullable()->change();
        });
    }
};
