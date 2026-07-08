<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatnot_buyers', function (Blueprint $table) {
            $table->id();
            $table->string('whatnot_user_id')->nullable()->unique()->comment('Whatnot internal user ID if available');
            $table->string('username')->unique();
            $table->string('display_name')->nullable();
            $table->string('avatar_url')->nullable();
            $table->unsignedInteger('total_orders')->default(0);
            $table->decimal('lifetime_spend', 12, 2)->default(0);
            $table->decimal('avg_order_value', 8, 2)->nullable();
            $table->date('first_purchase_date')->nullable()->index();
            $table->date('last_purchase_date')->nullable()->index();
            $table->json('raw_data')->nullable();
            $table->timestamps();

            $table->index('lifetime_spend');
            $table->index('total_orders');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatnot_buyers');
    }
};
