<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('chat_message_reactions', function (Blueprint $table) {
            $table->id();
            $table->string('message_id', 36)->index();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('emoji', 16);
            $table->timestamps();
            $table->unique(['message_id', 'user_id', 'emoji']);
        });
    }
    public function down(): void { Schema::dropIfExists('chat_message_reactions'); }
};
