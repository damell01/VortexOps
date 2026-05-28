<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_items', function (Blueprint $table): void {
            $table->string('barcode')->nullable()->after('sku');
            $table->decimal('seller_unit_cost', 10, 2)->default(0)->after('unit_cost');
            $table->decimal('shipping_unit_cost', 10, 2)->default(0)->after('seller_unit_cost');
            $table->decimal('other_unit_fees', 10, 2)->default(0)->after('shipping_unit_cost');
            $table->decimal('average_unit_cost', 10, 2)->nullable()->after('other_unit_fees');
            $table->json('cost_metadata')->nullable()->after('average_unit_cost');
            $table->text('cost_notes')->nullable()->after('notes');
        });

        Schema::create('inventory_containers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_location_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('parent_container_id')->nullable()->constrained('inventory_containers')->nullOnDelete();
            $table->string('container_type', 40);
            $table->string('label');
            $table->string('barcode')->nullable()->unique();
            $table->decimal('quantity', 10, 2)->default(0);
            $table->string('status', 40)->default('active');
            $table->boolean('scanner_ready')->default(false);
            $table->json('scanner_metadata')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['inventory_item_id', 'container_type']);
            $table->index(['inventory_location_id', 'status']);
            $table->index(['parent_container_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_containers');

        Schema::table('inventory_items', function (Blueprint $table): void {
            $table->dropColumn([
                'barcode',
                'seller_unit_cost',
                'shipping_unit_cost',
                'other_unit_fees',
                'average_unit_cost',
                'cost_metadata',
                'cost_notes',
            ]);
        });
    }
};
