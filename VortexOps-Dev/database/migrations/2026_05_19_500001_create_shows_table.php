<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatnot_channel_id')->nullable()->constrained('whatnot_channels')->nullOnDelete();
            $table->string('title')->nullable();
            $table->date('show_date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->unsignedInteger('units_sold')->default(0);
            $table->decimal('gross_revenue', 12, 2)->default(0);
            $table->decimal('whatnot_net', 12, 2)->default(0);
            $table->decimal('tips', 12, 2)->default(0);
            $table->unsignedInteger('show_duration')->nullable()->comment('minutes');
            $table->enum('import_source', ['manual', 'auto_whatnot'])->default('manual');
            $table->json('raw_import_payload')->nullable();
            $table->json('ai_streamer_suggestion')->nullable();
            $table->enum('status', [
                'draft',
                'pending_review',
                'mapping',
                'pending_approval',
                'reconciled',
                'closed',
                'cancelled',
            ])->default('draft');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shows');
    }
};
