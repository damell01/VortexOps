<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_insights', function (Blueprint $table) {
            $table->id();
            $table->string('category', 40)->index();
            $table->string('severity', 20)->default('info')->index();
            $table->string('title');
            $table->text('summary');
            $table->json('details')->nullable();
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('ai_task_id')->nullable()->constrained('ai_tasks')->nullOnDelete();
            $table->string('status', 20)->default('open')->index();
            $table->timestamp('generated_at')->nullable()->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();

            $table->index(['source_type', 'source_id']);
            $table->index(['category', 'status', 'generated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_insights');
    }
};
