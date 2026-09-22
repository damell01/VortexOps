<?php

namespace App\Console\Commands;

use App\Models\InventoryLot;
use App\Models\Product;
use App\Services\InventoryLotService;
use Illuminate\Console\Command;

/**
 * Covers on-hand stock that predates (or fell outside) FIFO lot tracking.
 *
 * Only pallets received through the current receiving paths open a lot as
 * they go. Anything received before that wiring existed — or through a path
 * that still doesn't cost it — leaves a gap between what InventoryStock says
 * is on the shelf and what the product's lots actually cover. Left alone,
 * that gap understates average_cost (it's computed only from covered
 * quantity) and lets it run out mid-sale. This closes the gap once with a
 * synthetic lot per product, priced at whatever cost is already known.
 */
class BackfillInventoryLots extends Command
{
    protected $signature = 'inventory:backfill-lots
        {--apply : Create the missing synthetic lots; without this, only previews them}';

    protected $description = "Cover on-hand stock that predates FIFO lot tracking with a synthetic lot, priced at each item's current cost.";

    public function handle(InventoryLotService $lots): int
    {
        $creatable = [];
        $unknownCost = [];

        Product::query()
            ->select(['id', 'name', 'sku', 'unit_cost', 'average_cost'])
            ->withSum('stock as on_hand', 'quantity')
            ->withSum(['lots as covered' => fn ($q) => $q->where('status', InventoryLot::STATUS_ACTIVE)], 'remaining_quantity')
            ->orderBy('id')
            ->chunkById(200, function ($products) use (&$creatable, &$unknownCost) {
                foreach ($products as $product) {
                    $gap = round((float) ($product->on_hand ?? 0) - (float) ($product->covered ?? 0), 2);

                    if ($gap <= 0) {
                        continue;
                    }

                    $cost = (float) ($product->costBasis() ?? 0);
                    $row  = ['id' => $product->id, 'name' => $product->name, 'sku' => $product->sku, 'gap' => $gap, 'cost' => $cost];

                    if ($cost > 0) {
                        $creatable[] = $row;
                    } else {
                        $unknownCost[] = $row;
                    }
                }
            });

        if (empty($creatable) && empty($unknownCost)) {
            $this->info("Every product's on-hand stock is already covered by a lot. Nothing to do.");
            return self::SUCCESS;
        }

        if (! empty($creatable)) {
            $this->table(
                ['Product', 'SKU', 'Uncovered qty', 'Backfill cost'],
                array_map(fn ($r) => [$r['name'], $r['sku'] ?: '—', number_format($r['gap'], 2), '$' . number_format($r['cost'], 4)], $creatable),
            );
        }

        if (! empty($unknownCost)) {
            $this->newLine();
            $this->warn('These have uncovered stock but no List Unit Cost or average to price it with — no lot can be created for them until one is set:');
            $this->table(
                ['Product', 'SKU', 'Uncovered qty'],
                array_map(fn ($r) => [$r['name'], $r['sku'] ?: '—', number_format($r['gap'], 2)], $unknownCost),
            );
        }

        if (empty($creatable)) {
            return self::SUCCESS;
        }

        if (! $this->option('apply')) {
            $this->newLine();
            $this->info('DRY RUN ONLY — no lots were created. Add --apply to create them.');
            return self::SUCCESS;
        }

        if (! $this->confirm(count($creatable) . ' synthetic lot(s) will be created for stock received before lot tracking covered it. Continue?', false)) {
            $this->info('No changes made.');
            return self::SUCCESS;
        }

        foreach ($creatable as $row) {
            $product = Product::find($row['id']);
            if (! $product) {
                continue;
            }

            InventoryLot::create([
                'product_id'         => $product->id,
                'quantity'           => $row['gap'],
                'unit_cost'          => $row['cost'],
                'remaining_quantity' => $row['gap'],
                'source'             => InventoryLot::SOURCE_SYNTHETIC,
                'status'             => InventoryLot::STATUS_ACTIVE,
                // Dated far in the past so FIFO consumes this before any lot
                // opened going forward — it stands in for stock nobody can
                // say exactly when it arrived.
                'received_at'        => now()->subYears(10),
            ]);

            $lots->recompute($product);
        }

        $this->info(count($creatable) . ' synthetic lot(s) created.');

        return self::SUCCESS;
    }
}
