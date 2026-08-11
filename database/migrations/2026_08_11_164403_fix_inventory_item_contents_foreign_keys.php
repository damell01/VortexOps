<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $connection = Schema::getConnection();

        // For SQLite, we need to recreate the table to fix foreign key references
        // since inventory_items was renamed to products
        if ($connection->getDriverName() === 'sqlite') {
            $connection->statement('PRAGMA foreign_keys=OFF');

            // Backup existing data
            $connection->statement('CREATE TABLE inventory_item_contents_backup AS SELECT * FROM inventory_item_contents');

            // Drop the old table
            Schema::dropIfExists('inventory_item_contents');

            // Recreate with correct foreign keys
            Schema::create('inventory_item_contents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('parent_inventory_item_id')
                    ->constrained('products')
                    ->onDelete('cascade');
                $table->foreignId('child_inventory_item_id')
                    ->constrained('products')
                    ->onDelete('cascade');
                $table->unsignedInteger('quantity_per_parent')->default(1);
                $table->string('unit_type')->nullable();
                $table->string('barcode')->nullable();
                $table->text('description')->nullable();
                $table->foreignId('created_by')
                    ->nullable()
                    ->constrained('users')
                    ->onDelete('set null');
                $table->timestamps();
                $table->softDeletes();

                $table->index('parent_inventory_item_id');
                $table->index('child_inventory_item_id');
                $table->index('barcode');
                $table->unique(['parent_inventory_item_id', 'child_inventory_item_id']);
            });

            // Restore data
            $connection->statement('INSERT INTO inventory_item_contents SELECT * FROM inventory_item_contents_backup');

            // Clean up backup
            Schema::dropIfExists('inventory_item_contents_backup');

            $connection->statement('PRAGMA foreign_keys=ON');
        } else {
            // For MySQL/PostgreSQL, drop and recreate constraints
            Schema::table('inventory_item_contents', function (Blueprint $table) {
                $table->dropForeign('inventory_item_contents_parent_inventory_item_id_foreign');
                $table->dropForeign('inventory_item_contents_child_inventory_item_id_foreign');
            });

            Schema::table('inventory_item_contents', function (Blueprint $table) {
                $table->foreign('parent_inventory_item_id')
                    ->references('id')
                    ->on('products')
                    ->onDelete('cascade');
                $table->foreign('child_inventory_item_id')
                    ->references('id')
                    ->on('products')
                    ->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $connection = Schema::getConnection();

        if ($connection->getDriverName() === 'sqlite') {
            $connection->statement('PRAGMA foreign_keys=OFF');

            $connection->statement('CREATE TABLE inventory_item_contents_backup AS SELECT * FROM inventory_item_contents');

            Schema::dropIfExists('inventory_item_contents');

            Schema::create('inventory_item_contents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('parent_inventory_item_id')
                    ->constrained('inventory_items')
                    ->onDelete('cascade');
                $table->foreignId('child_inventory_item_id')
                    ->constrained('inventory_items')
                    ->onDelete('cascade');
                $table->unsignedInteger('quantity_per_parent')->default(1);
                $table->string('unit_type')->nullable();
                $table->string('barcode')->nullable();
                $table->text('description')->nullable();
                $table->foreignId('created_by')
                    ->nullable()
                    ->constrained('users')
                    ->onDelete('set null');
                $table->timestamps();
                $table->softDeletes();

                $table->index('parent_inventory_item_id');
                $table->index('child_inventory_item_id');
                $table->index('barcode');
                $table->unique(['parent_inventory_item_id', 'child_inventory_item_id']);
            });

            $connection->statement('INSERT INTO inventory_item_contents SELECT * FROM inventory_item_contents_backup');

            Schema::dropIfExists('inventory_item_contents_backup');

            $connection->statement('PRAGMA foreign_keys=ON');
        }
    }
};
