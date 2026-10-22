<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('shows', function (Blueprint $table) {
            $table->timestamp('tips_verified_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('shows', function (Blueprint $table) {
            $table->dropIndex(['tips_verified_at']);
            $table->dropColumn('tips_verified_at');
        });
    }
};
