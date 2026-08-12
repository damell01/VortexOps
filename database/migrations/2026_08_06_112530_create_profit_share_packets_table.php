<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('profit_share_packets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('streamer_id')->constrained('streamers')->cascadeOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->year('year');
            $table->unsignedTinyInteger('month');
            $table->decimal('gross_revenue', 12, 2)->default(0);
            $table->decimal('product_cost', 12, 2)->default(0);
            $table->decimal('shipping_cost', 12, 2)->default(0);
            $table->decimal('other_costs', 12, 2)->default(0);
            $table->decimal('profit_share_pct', 5, 2)->nullable();
            $table->decimal('profit_share_amount', 12, 2)->default(0);
            $table->decimal('hourly_rate', 10, 2)->nullable();
            $table->decimal('hours_worked', 8, 2)->default(0);
            $table->decimal('hourly_earnings', 12, 2)->default(0);
            $table->string('status')->default('draft'); // draft, submitted, approved, rejected
            $table->text('notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();

            $table->index(['streamer_id', 'year', 'month']);
            $table->index(['manager_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('profit_share_packets');
    }
};
