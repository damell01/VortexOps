<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatnot_shows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatnot_channel_id')->nullable()->constrained('whatnot_channels')->nullOnDelete();
            $table->string('title')->nullable();
            $table->date('show_date');
            $table->dateTime('started_at')->nullable();
            $table->dateTime('ended_at')->nullable();
            $table->enum('status', ['draft', 'pending_reconciliation', 'reconciling', 'reconciled', 'paid'])->default('draft');
            $table->enum('source', ['manual', 'csv_import', 'scraper'])->default('manual');
            $table->text('notes')->nullable();
            $table->json('raw_data')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatnot_shows');
    }
};
