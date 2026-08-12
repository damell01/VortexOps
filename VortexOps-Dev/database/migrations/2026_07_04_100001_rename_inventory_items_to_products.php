<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('inventory_items') && ! Schema::hasTable('products')) {
            Schema::rename('inventory_items', 'products');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('products') && ! Schema::hasTable('inventory_items')) {
            Schema::rename('products', 'inventory_items');
        }
    }
};
