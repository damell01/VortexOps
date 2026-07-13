<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Move the embedding vector off `products` into `product_embeddings`.
 *
 * A vector is multi-KB JSON. On `products` — the hottest table in the app,
 * joined by inventory, orders, stock, and deductions — it bloats every read.
 * Splitting it out keeps the products table small (denser index/data pages,
 * cheaper SELECT *) and loads vectors only when semantic matching needs them.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('product_embeddings')) {
            Schema::create('product_embeddings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->unique()->constrained('products')->cascadeOnDelete();
                $table->json('vector');
                $table->timestamps();
            });
        }

        // Copy any existing vectors over before dropping the column.
        if (Schema::hasColumn('products', 'embedding')) {
            DB::table('products')
                ->whereNotNull('embedding')
                ->orderBy('id')
                ->select(['id', 'embedding'])
                ->chunkById(500, function ($rows) {
                    $now = now();
                    $insert = $rows->map(fn ($r) => [
                        'product_id' => $r->id,
                        'vector'     => $r->embedding,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all();

                    if (! empty($insert)) {
                        DB::table('product_embeddings')->insertOrIgnore($insert);
                    }
                });

            Schema::table('products', fn (Blueprint $table) => $table->dropColumn('embedding'));
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('products', 'embedding')) {
            Schema::table('products', fn (Blueprint $table) => $table->json('embedding')->nullable());
        }

        if (Schema::hasTable('product_embeddings')) {
            DB::table('product_embeddings')->orderBy('product_id')->chunkById(500, function ($rows) {
                foreach ($rows as $r) {
                    DB::table('products')->where('id', $r->product_id)->update(['embedding' => $r->vector]);
                }
            }, 'product_id');

            Schema::dropIfExists('product_embeddings');
        }
    }
};
