<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The Fulfillment Center's digital tracking record.
 *
 * A fulfillment run is the record for one show: it exists from the moment the
 * show is ready or assigned, gains a station and start time when packing
 * begins, and keeps every buyer letter, package, scan, issue, pause,
 * acknowledgment and approval. Important moments also land in an append-only
 * event log, which is what the show log and the camera lookup read.
 *
 * Every step checks what is already there, so the migration is safe to run
 * again and on databases that already ran parts of it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fulfillment_stations', function (Blueprint $table) {
            if (! Schema::hasColumn('fulfillment_stations', 'camera_label')) {
                $table->string('camera_label', 64)->nullable()->after('name');
            }
        });

        Schema::table('fulfillment_runs', function (Blueprint $table) {
            // A run now exists before packing starts (ready / assigned), so it
            // may not have a station yet.
            $table->unsignedBigInteger('station_id')->nullable()->change();
        });

        Schema::table('fulfillment_runs', function (Blueprint $table) {
            $add = fn (string $column) => ! Schema::hasColumn('fulfillment_runs', $column);
            if ($add('current_buyer_id')) $table->unsignedBigInteger('current_buyer_id')->nullable();
            if ($add('paused_at')) $table->timestamp('paused_at')->nullable();
            if ($add('paused_seconds')) $table->unsignedInteger('paused_seconds')->default(0);
            if ($add('buyer_count')) $table->unsignedInteger('buyer_count')->nullable();
            if ($add('order_count')) $table->unsignedInteger('order_count')->nullable();
            if ($add('expected_packages')) $table->unsignedInteger('expected_packages')->nullable();
            if ($add('completed_packages')) $table->unsignedInteger('completed_packages')->default(0);
            if ($add('discrepancies')) $table->json('discrepancies')->nullable();
            if ($add('ready_at')) $table->timestamp('ready_at')->nullable();
            if ($add('ready_notified_at')) $table->timestamp('ready_notified_at')->nullable();
            if ($add('acknowledged_at')) $table->timestamp('acknowledged_at')->nullable();
            if ($add('acknowledged_by')) $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            if ($add('approved_at')) $table->timestamp('approved_at')->nullable();
            if ($add('approved_by')) $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            if ($add('approval_notes')) $table->text('approval_notes')->nullable();
        });

        // The first version finished runs as 'done' while everything reading
        // them looked for 'completed', so finished runs never reached review.
        DB::table('fulfillment_runs')->where('status', 'done')->update(['status' => 'completed']);
        DB::table('fulfillment_runs')->whereNull('ready_at')->update(['ready_at' => DB::raw('created_at')]);

        Schema::table('fulfillment_run_buyers', function (Blueprint $table) {
            $add = fn (string $column) => ! Schema::hasColumn('fulfillment_run_buyers', $column);
            if ($add('order_count')) $table->unsignedInteger('order_count')->default(0);
            if ($add('package_count')) $table->unsignedInteger('package_count')->nullable();
        });

        if (! Schema::hasTable('fulfillment_run_packages')) {
            Schema::create('fulfillment_run_packages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('run_id')->constrained('fulfillment_runs')->cascadeOnDelete();
                $table->foreignId('buyer_id')->constrained('fulfillment_run_buyers')->cascadeOnDelete();
                // The shipment's id when one exists, else the buyer's orders.
                $table->string('package_key', 191);
                $table->foreignId('shipment_id')->nullable()->constrained('shipments')->nullOnDelete();
                $table->json('order_ids')->nullable();
                $table->boolean('is_pwe')->nullable();
                $table->string('status', 16)->default('waiting');
                $table->foreignId('packed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('packed_at')->nullable();
                $table->timestamps();
                // One completion per package: a second press is a no-op, not a second package.
                $table->unique(['run_id', 'package_key']);
                $table->index(['packed_by', 'packed_at']);
            });
        }

        if (! Schema::hasTable('fulfillment_events')) {
            Schema::create('fulfillment_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('run_id')->nullable()->constrained('fulfillment_runs')->cascadeOnDelete();
                $table->foreignId('show_id')->nullable()->constrained('shows')->nullOnDelete();
                $table->foreignId('station_id')->nullable()->constrained('fulfillment_stations')->nullOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->unsignedBigInteger('buyer_id')->nullable();
                $table->unsignedBigInteger('package_id')->nullable();
                $table->string('type', 40);
                $table->string('buyer_letter', 12)->nullable();
                $table->string('buyer_username')->nullable();
                $table->json('data')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['run_id', 'id']);
                $table->index(['type', 'created_at']);
                $table->index('buyer_username');
            });
        }

        Schema::table('fulfillment_flags', function (Blueprint $table) {
            $add = fn (string $column) => ! Schema::hasColumn('fulfillment_flags', $column);
            if ($add('package_id')) $table->unsignedBigInteger('package_id')->nullable();
            if ($add('station_id')) $table->unsignedBigInteger('station_id')->nullable();
            if ($add('reviewer_id')) $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            if ($add('blocking')) $table->boolean('blocking')->default(true);
        });

        Schema::table('fulfillment_verifications', function (Blueprint $table) {
            $add = fn (string $column) => ! Schema::hasColumn('fulfillment_verifications', $column);
            if ($add('package_id')) $table->unsignedBigInteger('package_id')->nullable();
            if ($add('note')) $table->string('note', 500)->nullable();
        });

        Schema::table('fulfillment_shifts', function (Blueprint $table) {
            $add = fn (string $column) => ! Schema::hasColumn('fulfillment_shifts', $column);
            if ($add('kind')) $table->string('kind', 8)->default('day');
            if ($add('status')) $table->string('status', 16)->default('scheduled');
        });

        if (! Schema::hasTable('fulfillment_shift_shows')) {
            Schema::create('fulfillment_shift_shows', function (Blueprint $table) {
                $table->id();
                $table->foreignId('shift_id')->constrained('fulfillment_shifts')->cascadeOnDelete();
                $table->foreignId('show_id')->constrained('shows')->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['shift_id', 'show_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fulfillment_shift_shows');
        Schema::dropIfExists('fulfillment_events');
        Schema::dropIfExists('fulfillment_run_packages');
        // Added columns stay: dropping them would discard tracking history.
    }
};
