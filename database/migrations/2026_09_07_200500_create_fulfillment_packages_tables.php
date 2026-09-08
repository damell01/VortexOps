<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('streamer_log_items', function (Blueprint $table) {
            if (! Schema::hasColumn('streamer_log_items', 'packed_quantity')) {
                $table->unsignedInteger('packed_quantity')->default(0)->after('quantity');
            }
        });

        if (! Schema::hasTable('fulfillment_packages')) {
            Schema::create('fulfillment_packages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('show_id')->constrained()->cascadeOnDelete();
                $table->foreignId('shipment_id')->nullable()->constrained('shipments')->nullOnDelete();
                $table->string('package_code')->unique();
                $table->unsignedInteger('box_number')->default(1);
                $table->unsignedInteger('box_total')->default(1);
                $table->string('buyer_username')->nullable();
                $table->string('tracking_number')->nullable();
                $table->string('carrier')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('packed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('status')->default('building');
                $table->timestamp('sealed_at')->nullable();
                $table->timestamp('label_printed_at')->nullable();
                $table->timestamp('shipped_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['show_id', 'status']);
                $table->index(['show_id', 'buyer_username']);
            });
        } else {
            Schema::table('fulfillment_packages', function (Blueprint $table) {
                if (! Schema::hasColumn('fulfillment_packages', 'show_id')) $table->foreignId('show_id')->nullable()->constrained()->cascadeOnDelete();
                if (! Schema::hasColumn('fulfillment_packages', 'shipment_id')) $table->foreignId('shipment_id')->nullable()->constrained('shipments')->nullOnDelete();
                if (! Schema::hasColumn('fulfillment_packages', 'package_code')) $table->string('package_code')->nullable()->unique();
                if (! Schema::hasColumn('fulfillment_packages', 'box_number')) $table->unsignedInteger('box_number')->default(1);
                if (! Schema::hasColumn('fulfillment_packages', 'box_total')) $table->unsignedInteger('box_total')->default(1);
                if (! Schema::hasColumn('fulfillment_packages', 'buyer_username')) $table->string('buyer_username')->nullable();
                if (! Schema::hasColumn('fulfillment_packages', 'packed_by')) $table->foreignId('packed_by')->nullable()->constrained('users')->nullOnDelete();
                if (! Schema::hasColumn('fulfillment_packages', 'sealed_at')) $table->timestamp('sealed_at')->nullable();
                if (! Schema::hasColumn('fulfillment_packages', 'label_printed_at')) $table->timestamp('label_printed_at')->nullable();
            });
        }

        if (! Schema::hasTable('fulfillment_package_items')) {
            Schema::create('fulfillment_package_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('fulfillment_package_id')->constrained()->cascadeOnDelete();
                $table->foreignId('streamer_log_item_id')->constrained()->cascadeOnDelete();
                $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
                $table->unsignedInteger('quantity')->default(1);
                $table->foreignId('packed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('packed_at')->nullable();
                $table->timestamps();

                $table->unique(['fulfillment_package_id', 'streamer_log_item_id']);
            });
        } else {
            Schema::table('fulfillment_package_items', function (Blueprint $table) {
                if (! Schema::hasColumn('fulfillment_package_items', 'streamer_log_item_id')) $table->foreignId('streamer_log_item_id')->nullable()->constrained()->cascadeOnDelete();
                if (! Schema::hasColumn('fulfillment_package_items', 'packed_by')) $table->foreignId('packed_by')->nullable()->constrained('users')->nullOnDelete();
                if (! Schema::hasColumn('fulfillment_package_items', 'packed_at')) $table->timestamp('packed_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('fulfillment_package_items')) {
            Schema::table('fulfillment_package_items', function (Blueprint $table) {
                foreach (['streamer_log_item_id', 'packed_by', 'packed_at'] as $column) {
                    if (Schema::hasColumn('fulfillment_package_items', $column)) $table->dropColumn($column);
                }
            });
        }

        if (Schema::hasTable('fulfillment_packages')) {
            Schema::table('fulfillment_packages', function (Blueprint $table) {
                foreach (['show_id', 'shipment_id', 'package_code', 'box_number', 'box_total', 'buyer_username', 'packed_by', 'sealed_at', 'label_printed_at'] as $column) {
                    if (Schema::hasColumn('fulfillment_packages', $column)) $table->dropColumn($column);
                }
            });
        }

        if (Schema::hasColumn('streamer_log_items', 'packed_quantity')) {
            Schema::table('streamer_log_items', function (Blueprint $table) {
                $table->dropColumn('packed_quantity');
            });
        }
    }
};
