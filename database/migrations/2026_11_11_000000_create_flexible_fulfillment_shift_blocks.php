<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('fulfillment_shift_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('kind', 20)->default('custom');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
        Schema::create('fulfillment_shift_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->nullable()->constrained('fulfillment_shift_templates')->nullOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('label', 80);
            $table->string('kind', 20)->default('custom');
            $table->string('status', 20)->default('scheduled');
            $table->unsignedSmallInteger('required_staff')->default(1);
            $table->foreignId('station_id')->nullable()->constrained('fulfillment_stations')->nullOnDelete();
            $table->foreignId('show_id')->nullable()->constrained('shows')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['starts_at', 'status'], 'fulfillment_blocks_week_status_idx');
        });
        Schema::create('fulfillment_shift_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_block_id')->constrained('fulfillment_shift_blocks')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('station_id')->nullable()->constrained('fulfillment_stations')->nullOnDelete();
            $table->timestamps();
            $table->unique(['shift_block_id', 'user_id'], 'fulfillment_block_employee_unique');
            $table->index(['user_id', 'shift_block_id'], 'fulfillment_assignment_employee_idx');
        });
        // Template changes affect future shifts only: blocks store their own
        // immutable start/end snapshots, not a live computed template time.
        \Illuminate\Support\Facades\DB::table('fulfillment_shift_templates')->insert([
            ['name' => 'Day Team', 'kind' => 'day', 'starts_at' => '06:00:00', 'ends_at' => '18:00:00', 'active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Night Team', 'kind' => 'night', 'starts_at' => '18:00:00', 'ends_at' => '06:00:00', 'active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('fulfillment_shift_assignments');
        Schema::dropIfExists('fulfillment_shift_blocks');
        Schema::dropIfExists('fulfillment_shift_templates');
    }
};
