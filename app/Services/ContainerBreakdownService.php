<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryStock;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Breaking a container down into the items it holds.
 *
 * A case of 12 boxes leaves the shelf as one unit and comes back as twelve.
 * The container's recorded contents (InventoryItemContent) say what "twelve"
 * means; this turns that description into actual stock movements so the
 * catalogue matches the shelf.
 */
class ContainerBreakdownService
{
    public function __construct(private InventoryService $inventory) {}

    /**
     * Break `$count` containers open at `$location`.
     *
     * Deducts the containers and credits each child at the same location, in
     * one transaction — a partial breakdown would leave stock that exists in
     * neither form.
     *
     * @return array{container: string, containers_broken: int, produced: array<int, array{name: string, quantity: int}>}
     */
    public function break(InventoryItem $container, InventoryLocation $location, int $count = 1): array
    {
        if ($count < 1) {
            throw new RuntimeException('Break at least one container.');
        }

        if (! $container->is_container) {
            throw new RuntimeException("\"{$container->name}\" is not marked as a container.");
        }

        $contents = $container->childContents()->with('childItem')->get()
            ->filter(fn ($line) => $line->childItem !== null);

        if ($contents->isEmpty()) {
            throw new RuntimeException(
                "\"{$container->name}\" has no contents recorded, so there is nothing to break it into. "
                . 'Add its contents first — the Container Scan page does this.'
            );
        }

        $onHand = (float) (InventoryStock::where('inventory_item_id', $container->getKey())
            ->where('inventory_location_id', $location->getKey())
            ->value('quantity') ?? 0);

        if ($onHand < $count) {
            throw new RuntimeException(
                "Only {$onHand} of \"{$container->name}\" on hand at {$location->name} — cannot break {$count}."
            );
        }

        $allocation = $this->allocateCost($container, $contents, $count);

        return DB::transaction(function () use ($container, $location, $count, $contents, $allocation) {
            $reason = "Broke {$count} × {$container->name} into its contents";

            $this->inventory->deductStock($container, $location, (float) $count, $reason);

            $produced = [];

            foreach ($contents as $line) {
                $child = $line->childItem;
                $qty   = (int) $line->quantity_per_parent * $count;

                if ($qty < 1) {
                    continue;
                }

                $this->inventory->addStock(
                    $child,
                    $location,
                    (float) $qty,
                    'breakdown',
                    "From breaking {$count} × {$container->name}",
                    $allocation[$line->getKey()] ?? null,
                );

                $produced[] = [
                    'name'      => $child->name,
                    'quantity'  => $qty,
                    'unit_cost' => $allocation[$line->getKey()] ?? null,
                ];
            }

            return [
                'container'         => $container->name,
                'containers_broken' => $count,
                'cost_released'     => $this->containerUnitCost($container) * $count,
                'produced'          => $produced,
            ];
        });
    }

    /**
     * What each child should be costed at, keyed by content line.
     *
     * Breaking a case does not create or destroy value: the money paid for the
     * case moves onto the things that came out of it. Costing the children at
     * their own existing average instead — which is what this used to do —
     * broke that both ways. A child never bought separately had no average, so
     * a $1,428 case became twelve boxes worth nothing and the value simply left
     * the books. A child that did have one got credited at that price
     * regardless of what the case cost, inventing value out of nothing.
     *
     * Where the children have known costs the split follows their relative
     * value, so a case holding one expensive box and one cheap one does not
     * price them the same. Where none of them do, it falls back to an equal
     * share per unit — arbitrary, but it conserves the total, which is the
     * property that matters.
     *
     * @param  \Illuminate\Support\Collection  $contents
     * @return array<int, float|null> content line id => unit cost
     */
    private function allocateCost(InventoryItem $container, $contents, int $count): array
    {
        $totalCost = $this->containerUnitCost($container) * $count;

        if ($totalCost <= 0) {
            // Nothing recorded against the container, so there is nothing
            // honest to pass on. Leaving the children's costs alone beats
            // overwriting them with a zero.
            return [];
        }

        $weights = [];

        foreach ($contents as $line) {
            $qty = (int) $line->quantity_per_parent * $count;

            if ($qty < 1) {
                continue;
            }

            $childCost = (float) ($line->childItem->average_cost ?: $line->childItem->unit_cost ?: 0);

            $weights[$line->getKey()] = ['qty' => $qty, 'weight' => $qty * $childCost];
        }

        if ($weights === []) {
            return [];
        }

        $totalWeight = array_sum(array_column($weights, 'weight'));

        // No child has a cost on record: weight by quantity alone.
        if ($totalWeight <= 0) {
            $totalQty = array_sum(array_column($weights, 'qty'));

            return array_map(
                fn (array $w): float => round($totalCost / $totalQty, 4),
                $weights,
            );
        }

        return array_map(
            fn (array $w): float => $w['weight'] > 0
                ? round(($totalCost * $w['weight'] / $totalWeight) / $w['qty'], 4)
                : 0.0,
            $weights,
        );
    }

    /** What one container is carried at — its average, or the price paid. */
    private function containerUnitCost(InventoryItem $container): float
    {
        return (float) ($container->average_cost ?: $container->unit_cost ?: 0);
    }
}
