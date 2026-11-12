<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\InventoryItemDeleter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Items deleted before every delete wrote off its stock can still have units
 * recorded at a location. This writes those off, through the inventory log
 * ("Written off — leftover stock of a deleted item"), and leaves the items
 * deleted. Dry run unless --apply.
 */
class WriteOffDeletedItemStock extends Command
{
    protected $signature = 'inventory:write-off-deleted {--apply : Write the stock off (otherwise only list it)}';

    protected $description = 'Write off stock still recorded on deleted inventory items, with an inventory log entry for each';

    public function handle(InventoryItemDeleter $deleter): int
    {
        $rows = DB::table('inventory_stock as s')->join('products as p', 'p.id', '=', 's.inventory_item_id')
            ->leftJoin('inventory_locations as l', 'l.id', '=', 's.inventory_location_id')
            ->whereNotNull('p.deleted_at')->where('s.quantity', '>', 0)
            ->orderBy('p.name')->get(['p.id', 'p.name', 'p.sku', 'l.name as location', 's.quantity']);

        if ($rows->isEmpty()) {
            $this->info('No deleted item has stock recorded. Nothing to do.');

            return self::SUCCESS;
        }

        $this->table(['Item', 'SKU', 'Location', 'Units'], $rows->map(fn ($r) => [$r->name, $r->sku ?: '—', trim((string) $r->location) ?: '—', (float) $r->quantity])->all());
        if (! $this->option('apply')) {
            $this->line($rows->count() . ' stock line(s) on deleted items. Run with --apply to write them off.');

            return self::SUCCESS;
        }

        $units = 0.0;
        foreach ($rows->pluck('id')->unique() as $id) {
            $item = Product::withTrashed()->find($id);
            if (! $item) continue;
            DB::transaction(function () use ($item, $deleter, &$units) {
                // Brought back for a moment so the write-off goes through the normal
                // stock path (it does not see a deleted item's rows), then deleted again.
                Product::withoutEvents(fn () => $item->restore());
                $units += $deleter->writeOffRemaining($item, 'Written off — leftover stock of a deleted item');
                Product::withoutEvents(fn () => $item->delete());
                activity('inventory')->performedOn($item)->causedBy(auth()->user())->log('Leftover stock of deleted item written off');
            });
        }
        $this->info('Wrote off ' . rtrim(rtrim(number_format($units, 2), '0'), '.') . ' unit(s) across ' . $rows->count() . ' stock line(s). The items stay deleted.');

        return self::SUCCESS;
    }
}
