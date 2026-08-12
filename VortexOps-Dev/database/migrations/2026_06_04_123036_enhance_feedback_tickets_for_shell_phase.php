<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feedback_tickets', function (Blueprint $table) {
            $table->string('type')->default('bug')->after('priority');
            $table->text('annotation_json')->nullable()->after('screenshot_path');
            $table->string('resource_type')->nullable()->after('page_url');
            $table->unsignedBigInteger('resource_id')->nullable()->after('resource_type');
        });
    }

    public function down(): void
    {
        Schema::table('feedback_tickets', function (Blueprint $table) {
            $table->dropColumn(['type', 'annotation_json', 'resource_type', 'resource_id']);
        });
    }
};
