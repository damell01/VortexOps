<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('brand')->nullable()->after('category');
            $table->string('sport')->nullable()->after('brand');
            $table->smallInteger('year')->unsigned()->nullable()->after('sport');
            $table->string('set_name')->nullable()->after('year');
            $table->string('product_type')->nullable()->after('set_name');   // Hobby Box, Blaster, Jumbo, etc.
            $table->string('configuration')->nullable()->after('product_type'); // "12 packs/box", "36 packs/box"
            $table->string('manufacturer')->nullable()->after('configuration');
            $table->string('upc', 20)->nullable()->unique()->after('manufacturer');
            $table->json('embedding')->nullable()->after('upc');             // product-level embedding (nomic-embed-text)
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['brand', 'sport', 'year', 'set_name', 'product_type', 'configuration', 'manufacturer', 'upc', 'embedding']);
        });
    }
};
