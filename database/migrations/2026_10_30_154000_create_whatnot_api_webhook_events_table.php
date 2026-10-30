<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('whatnot_api_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('webhook_id', 64)->unique();
            $table->string('seller_id', 100);
            $table->string('topic', 150);
            $table->string('payload_sha256', 64);
            $table->longText('encrypted_payload');
            $table->string('status', 24)->default('pending');
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->index(['seller_id', 'status']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('whatnot_api_webhook_events');
    }
};
