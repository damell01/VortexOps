<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A picture of the product.
 *
 * Stock is identified off a shelf by sight long before anyone reads a SKU, and
 * a wall of near-identical card product names ("2026 Topps Chrome Hobby" beside
 * "2026 Topps Chrome Hobby Jumbo") is exactly where a thumbnail earns its keep.
 *
 * One image, not a gallery: the question being answered is "is this the right
 * box", which one photo settles. A product_images table can come later if
 * condition or variant shots turn out to be wanted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('image_path')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('image_path');
        });
    }
};
