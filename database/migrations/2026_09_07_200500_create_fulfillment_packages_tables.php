<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('streamer_log_items', function (Blueprint $table) {
            $table->unsignedInteger('packed_quantity')->default(0)->after('quantity');
        });

        Schema::create('fulfillment_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('show_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shipment_id')->nullable()->constrained('shipments')->nullOnDelete();
            $table->string('package_code')->unique();
            $table->unsignedInteger('box_number')->default(1);
            $table->unsignedInteger('box_total')->default(1);
            $table->string('buyer_username')->nullable();
            $table->string('status')->default('building');
            $table->foreignId('packed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sealed_at')->nullable();
            $table->timestamp('label_printed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['show_id', 'status']);
            $table->index(['show_id', 'buyer_username']);
        });

        Schema::create('fulfillment_package_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fulfillment_package_id')->constrained()->cascadeOnDelete();
            $table->foreignId('streamer_log_item_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->foreignId('packed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('packed_at')->nullable();
            $table->timestamps();

            $table->unique(['fulfillment_package_id', 'streamer_log_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fulfillment_package_items');
        Schema::dropIfExists('fulfillment_packages');

        Schema::table('streamer_log_items', function (Blueprint $table) {
            $table->dropColumn('packed_quantity');
        });
    }
};
