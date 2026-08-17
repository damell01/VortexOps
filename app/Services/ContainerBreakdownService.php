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

        return DB::transaction(function () use ($container, $location, $count, $contents) {
            $reason = "Broke {$count} × {$container->name} into its contents";

            $this->inventory->deductStock($container, $location, (float) $count, $reason);

            $produced = [];

            foreach ($contents as $line) {
                $child = $line->childItem;
                $qty   = (int) $line->quantity_per_parent * $count;

                if ($qty < 1) {
                    continue;
                }

                // Costed from the child's own average where it has one, so
                // breaking a case doesn't silently rewrite its valuation.
                $this->inventory->addStock(
                    $child,
                    $location,
                    (float) $qty,
                    'breakdown',
                    "From breaking {$count} × {$container->name}",
                    $child->average_cost > 0 ? (float) $child->average_cost : null,
                );

                $produced[] = ['name' => $child->name, 'quantity' => $qty];
            }

            return [
                'container'         => $container->name,
                'containers_broken' => $count,
                'produced'          => $produced,
            ];
        });
    }
}
