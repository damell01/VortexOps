<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fast exact SKU/UPC/barcode lookups and stock-location joins.
 * No rows are changed; index creation should be scheduled off-peak.
 */
return new class extends Migration {
    private array $indexes = [
        'products' => [
            'vx_product_barcode_lookup_idx' => ['barcode'],
            'vx_product_upc_lookup_idx' => ['upc'],
            'vx_product_sku_lookup_idx' => ['sku'],
        ],
        'inventory_stock' => [
            'vx_stock_item_location_idx' => ['inventory_item_id', 'inventory_location_id'],
            'vx_stock_location_item_idx' => ['inventory_location_id', 'inventory_item_id'],
        ],
        'inventory_movements' => [
            'vx_move_item_created_idx' => ['inventory_item_id', 'created_at'],
            'vx_move_to_created_idx' => ['to_location_id', 'created_at'],
        ],
    ];

    public function up(): void
    {
        foreach ($this->indexes as $table => $indexes) {
            if (! Schema::hasTable($table)) continue;
            foreach ($indexes as $name => $columns) {
                if (count(array_filter($columns, fn ($column) => Schema::hasColumn($table, $column))) !== count($columns)) continue;
                // Skip an equivalent existing index to avoid redundant write overhead.
                $existing = Schema::getIndexes($table);
                if (collect($existing)->contains(fn ($index) => ($index['columns'] ?? []) === $columns)) continue;
                Schema::table($table, fn (Blueprint $t) => $t->index($columns, $name));
            }
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as $table => $indexes) {
            if (! Schema::hasTable($table)) continue;
            $names = array_column(Schema::getIndexes($table), 'name');
            foreach ($indexes as $name => $columns) {
                if (in_array($name, $names, true)) Schema::table($table, fn (Blueprint $t) => $t->dropIndex($name));
            }
        }
    }
};
