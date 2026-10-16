<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('web_push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('endpoint_hash', 64)->unique();
            $table->text('endpoint');
            $table->text('public_key');
            $table->text('auth_token');
            $table->string('content_encoding', 20)->default('aes128gcm');
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('web_push_subscriptions'); }
};
