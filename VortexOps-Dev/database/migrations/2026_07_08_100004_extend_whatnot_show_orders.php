<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add columns idempotently (a prior partial run may have added some).
        Schema::table('whatnot_show_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('whatnot_show_orders', 'whatnot_buyer_id')) {
                $table->unsignedBigInteger('whatnot_buyer_id')->nullable()->after('show_id');
            }
            if (! Schema::hasColumn('whatnot_show_orders', 'inventory_item_id')) {
                $table->unsignedBigInteger('inventory_item_id')->nullable()->after('whatnot_buyer_id');
            }
            if (! Schema::hasColumn('whatnot_show_orders', 'shipping_amount')) {
                $table->decimal('shipping_amount', 8, 2)->nullable()->after('total_price');
            }
            if (! Schema::hasColumn('whatnot_show_orders', 'tax_amount')) {
                $table->decimal('tax_amount', 8, 2)->nullable()->after('shipping_amount');
            }
            if (! Schema::hasColumn('whatnot_show_orders', 'fees_amount')) {
                $table->decimal('fees_amount', 8, 2)->nullable()->after('tax_amount');
            }
            if (! Schema::hasColumn('whatnot_show_orders', 'net_amount')) {
                $table->decimal('net_amount', 8, 2)->nullable()->after('fees_amount');
            }
            if (! Schema::hasColumn('whatnot_show_orders', 'tracking_number')) {
                $table->string('tracking_number')->nullable()->after('status');
            }
            if (! Schema::hasColumn('whatnot_show_orders', 'shipping_status')) {
                $table->string('shipping_status')->nullable()->after('tracking_number');
            }
        });

        // Foreign keys are best-effort: only add them when the referenced table
        // exists, and never let a constraint that can't be formed on this
        // database abort the whole migration. The Eloquent relations work without
        // the DB-level FK; this just adds referential integrity where possible.
        foreach ([
            ['whatnot_buyer_id', 'whatnot_buyers'],
            ['inventory_item_id', 'inventory_items'],
        ] as [$column, $referenced]) {
            if (! Schema::hasTable($referenced)) {
                continue;
            }
            try {
                Schema::table('whatnot_show_orders', function (Blueprint $table) use ($column, $referenced) {
                    $table->foreign($column)->references('id')->on($referenced)->nullOnDelete();
                });
            } catch (\Throwable $e) {
                // Constraint already present or can't be formed here — skip.
            }
        }

        // Indexes, also best-effort (skip if already present).
        foreach (['whatnot_buyer_id', 'inventory_item_id'] as $column) {
            try {
                Schema::table('whatnot_show_orders', fn (Blueprint $table) => $table->index($column));
            } catch (\Throwable $e) {
                // Already indexed — skip.
            }
        }
    }

    public function down(): void
    {
        Schema::table('whatnot_show_orders', function (Blueprint $table) {
            foreach (['whatnot_buyer_id', 'inventory_item_id'] as $column) {
                try { $table->dropForeign([$column]); } catch (\Throwable $e) {}
                try { $table->dropIndex([$column]); } catch (\Throwable $e) {}
            }
        });

        Schema::table('whatnot_show_orders', function (Blueprint $table) {
            foreach ([
                'whatnot_buyer_id', 'inventory_item_id',
                'shipping_amount', 'tax_amount', 'fees_amount', 'net_amount',
                'tracking_number', 'shipping_status',
            ] as $column) {
                if (Schema::hasColumn('whatnot_show_orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
