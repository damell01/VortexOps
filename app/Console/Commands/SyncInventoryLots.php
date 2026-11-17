<?php

namespace App\Console\Commands;

use App\Models\InventoryLot;
use App\Models\Product;
use App\Services\InventoryLotService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Makes each product's average cost the average of what is actually on hand.
 *
 * A product's lots can claim more units than its shelves hold: stock sold or
 * written off before lot tracking existed, or through a path that didn't use
 * up a lot. Those leftover "remaining" units keep a sold-out wave in the
 * average forever (first wave $100, all sold; second wave $50 on hand — the
 * average should read $50, not $75). This uses up the excess from the oldest
 * lots first, the same FIFO order a sale uses, then recomputes the average
 * from the lots that are left.
 *
 * The opposite gap (stock no lot covers) is inventory:backfill-lots.
 */
class SyncInventoryLots extends Command
{
    protected $signature = 'inventory:sync-lots
        {--apply : Use up the excess and recompute; without this, only previews}';

    protected $description = 'Average cost = average of what is on hand: use up lot quantity that is no longer on the shelves (oldest first) and recompute.';

    public function handle(InventoryLotService $lots): int
    {
        $rows = self::plan();

        if ($rows === []) {
            $this->info("Every product's lots match what is on hand. Averages are already on-hand averages.");
        } else {
            $this->table(['Product', 'SKU', 'On hand', 'Lots say', 'Use up', 'Avg now', 'Avg after'],
                array_map(fn ($r) => [$r['name'], $r['sku'] ?: '—', $r['on_hand'], $r['covered'], $r['excess'], '$'.number_format($r['avg_now'], 2), $r['avg_after'] === null ? 'kept' : '$'.number_format($r['avg_after'], 2)], $rows));
        }

        if (! $this->option('apply')) {
            $this->newLine();
            $this->info('DRY RUN — nothing changed. Add --apply to use up the excess and recompute every average.');

            return self::SUCCESS;
        }

        $fixed = self::apply($lots);
        $this->info("Used up excess lot stock on {$fixed['excess']} product(s); recomputed {$fixed['recomputed']} average(s).");

        return self::SUCCESS;
    }

    /** What would change: products whose lots claim more than is on the shelves. */
    public static function plan(): array
    {
        $rows = [];
        Product::query()
            ->select(['id', 'name', 'sku', 'average_cost'])
            ->withSum('stock as on_hand', 'quantity')
            ->withSum(['lots as covered' => fn ($q) => $q->where('status', InventoryLot::STATUS_ACTIVE)->where('remaining_quantity', '>', 0)], 'remaining_quantity')
            ->orderBy('id')
            ->chunkById(500, function ($products) use (&$rows) {
                foreach ($products as $p) {
                    $onHand = max(0, (float) ($p->on_hand ?? 0));
                    $excess = round((float) ($p->covered ?? 0) - $onHand, 4);
                    if ($excess <= 0.0001) continue;
                    $rows[] = ['id' => $p->id, 'name' => $p->name, 'sku' => $p->sku, 'on_hand' => $onHand, 'covered' => (float) $p->covered,
                        'excess' => $excess, 'avg_now' => (float) $p->average_cost, 'avg_after' => self::averageAfter($p->id, $excess)];
                }
            });

        return $rows;
    }

    /** The average of the lots left once the oldest $excess units are gone (null: nothing left, the last value is kept). */
    private static function averageAfter(int $productId, float $excess): ?float
    {
        $val = 0.0; $qty = 0.0;
        foreach (InventoryLot::where('product_id', $productId)->where('status', InventoryLot::STATUS_ACTIVE)->where('remaining_quantity', '>', 0)
            ->orderBy('received_at')->orderBy('id')->get(['remaining_quantity', 'unit_cost']) as $lot) {
            $left = (float) $lot->remaining_quantity;
            $take = min($left, $excess);
            $excess -= $take;
            $left -= $take;
            $val += $left * (float) $lot->unit_cost;
            $qty += $left;
        }

        return $qty > 0 ? $val / $qty : null;
    }

    /** @return array{excess: int, recomputed: int} */
    public static function apply(InventoryLotService $lots): array
    {
        $excess = 0;
        foreach (self::plan() as $row) {
            $product = Product::find($row['id']);
            if (! $product) continue;
            DB::transaction(fn () => $lots->consume($product, $row['excess']));
            $excess++;
        }

        // Recompute every product that still has stock in lots, so an average
        // written some other way (an import, an older receiving path) is
        // replaced by the on-hand one.
        $recomputed = 0;
        Product::query()->whereHas('lots', fn ($q) => $q->where('status', InventoryLot::STATUS_ACTIVE)->where('remaining_quantity', '>', 0))
            ->select(['id', 'average_cost'])->chunkById(500, function ($products) use ($lots, &$recomputed) {
                foreach ($products as $product) {
                    $lots->recompute($product);
                    $recomputed++;
                }
            });

        return ['excess' => $excess, 'recomputed' => $recomputed];
    }
}
