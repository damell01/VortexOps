<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_identities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained('vendors')->nullOnDelete();

            // upc | barcode | vendor_sku | manufacturer_sku | alias | search_token
            $table->string('type', 30);
            $table->string('value');                       // the actual identity value
            $table->json('embedding')->nullable();         // vector for text-based identities

            // Learning / confidence
            $table->unsignedInteger('times_confirmed')->default(0);
            $table->timestamp('last_confirmed_at')->nullable();
            $table->decimal('auto_confidence', 5, 4)->nullable(); // 0.0000–1.0000

            // Audit
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->index(['type', 'value']);
            $table->index('product_id');
            $table->unique(['type', 'value', 'vendor_id'], 'product_identities_type_value_vendor_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_identities');
    }
};
