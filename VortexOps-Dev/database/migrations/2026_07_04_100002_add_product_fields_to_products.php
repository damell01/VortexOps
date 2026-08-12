<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'brand'))
                $table->string('brand')->nullable()->after('category');
            if (! Schema::hasColumn('products', 'sport'))
                $table->string('sport')->nullable()->after('brand');
            if (! Schema::hasColumn('products', 'year'))
                $table->smallInteger('year')->unsigned()->nullable()->after('sport');
            if (! Schema::hasColumn('products', 'set_name'))
                $table->string('set_name')->nullable()->after('year');
            if (! Schema::hasColumn('products', 'product_type'))
                $table->string('product_type')->nullable()->after('set_name');
            if (! Schema::hasColumn('products', 'configuration'))
                $table->string('configuration')->nullable()->after('product_type');
            if (! Schema::hasColumn('products', 'manufacturer'))
                $table->string('manufacturer')->nullable()->after('configuration');
            if (! Schema::hasColumn('products', 'upc'))
                $table->string('upc', 20)->nullable()->unique()->after('manufacturer');
            if (! Schema::hasColumn('products', 'embedding'))
                $table->json('embedding')->nullable()->after('upc');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['brand', 'sport', 'year', 'set_name', 'product_type', 'configuration', 'manufacturer', 'upc', 'embedding']);
        });
    }
};
