<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('whatnot_api_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatnot_channel_id')->unique()->constrained('whatnot_channels')->cascadeOnDelete();
            $table->text('access_token')->nullable();
            $table->boolean('enabled')->default(false);
            $table->boolean('sync_enabled')->default(false);
            $table->string('status')->default('not_configured');
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatnot_api_connections');
    }
};
