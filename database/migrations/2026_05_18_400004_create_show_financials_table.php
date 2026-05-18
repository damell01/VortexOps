<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('show_financials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatnot_show_id')->unique()->constrained('whatnot_shows')->cascadeOnDelete();
            $table->decimal('gross_sales', 12, 2)->default(0);
            $table->decimal('platform_fee_pct', 5, 2)->default(0);
            $table->decimal('platform_fee_amount', 12, 2)->default(0);
            $table->decimal('shipping_collected', 12, 2)->default(0);
            $table->decimal('tips_collected', 12, 2)->default(0);
            $table->decimal('owner_platform_fee_pct', 5, 2)->default(0);
            $table->decimal('net_revenue', 12, 2)->default(0);
            $table->decimal('cogs', 12, 2)->default(0);
            $table->decimal('gross_profit', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('show_financials');
    }
};
