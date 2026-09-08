<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fulfillment_package_items')) {
            return;
        }

        Schema::table('fulfillment_package_items', function (Blueprint $table) {
            // A streamer can log a physical item before it is mapped to inventory.
            // Packing must still work, so product_id is optional for the new flow.
            if (Schema::hasColumn('fulfillment_package_items', 'product_id')) {
                $table->foreignId('product_id')->nullable()->change();
            }
        });

        // The legacy package schema allowed only one row per product per box.
        // Physical packing is line-based: the same catalog product may appear on
        // multiple streamer log lines, so uniqueness belongs to the source line.
        try {
            Schema::table('fulfillment_package_items', function (Blueprint $table) {
                $table->dropUnique('pkg_items_pkg_product_unique');
            });
        } catch (\Throwable) {
            // Fresh installs use the newer schema and never had the legacy key.
        }

        if (Schema::hasColumn('fulfillment_package_items', 'streamer_log_item_id')) {
            try {
                Schema::table('fulfillment_package_items', function (Blueprint $table) {
                    $table->unique(
                        ['fulfillment_package_id', 'streamer_log_item_id'],
                        'pkg_items_pkg_streamer_line_unique'
                    );
                });
            } catch (\Throwable) {
                // The fresh-install migration already creates an equivalent key.
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('fulfillment_package_items')) {
            return;
        }

        try {
            Schema::table('fulfillment_package_items', function (Blueprint $table) {
                $table->dropUnique('pkg_items_pkg_streamer_line_unique');
            });
        } catch (\Throwable) {
        }

        Schema::table('fulfillment_package_items', function (Blueprint $table) {
            if (Schema::hasColumn('fulfillment_package_items', 'product_id')) {
                $table->foreignId('product_id')->nullable(false)->change();
            }
        });

        try {
            Schema::table('fulfillment_package_items', function (Blueprint $table) {
                $table->unique(
                    ['fulfillment_package_id', 'product_id'],
                    'pkg_items_pkg_product_unique'
                );
            });
        } catch (\Throwable) {
        }
    }
};