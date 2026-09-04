<?php

namespace App\Console\Commands;

use App\Models\InventoryMovement;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CompactInventoryMovements extends Command
{
    protected $signature = 'inventory:compact-movements {--dry-run : Show groups that would be collapsed without changing data}';

    protected $description = 'Collapse duplicate pallet receipt audit rows into one row with the summed quantity';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $groups = InventoryMovement::query()
            ->where('movement_type', 'opening')
            ->where('reason', 'like', 'Received via pallet #%')
            ->select([
                'inventory_item_id', 'from_location_id', 'to_location_id',
                'movement_type', 'reason', 'created_by',
            ])
            ->selectRaw('COUNT(*) as row_count, SUM(quantity) as total_quantity')
            ->groupBy([
                'inventory_item_id', 'from_location_id', 'to_location_id',
                'movement_type', 'reason', 'created_by',
            ])
            ->havingRaw('COUNT(*) > 1')
            ->get();

        $collapsedGroups = 0;
        $removedRows = 0;

        foreach ($groups as $group) {
            $query = InventoryMovement::query()
                ->where('inventory_item_id', $group->inventory_item_id)
                ->where('movement_type', $group->movement_type)
                ->where('reason', $group->reason)
                ->when($group->from_location_id === null, fn ($q) => $q->whereNull('from_location_id'), fn ($q) => $q->where('from_location_id', $group->from_location_id))
                ->when($group->to_location_id === null, fn ($q) => $q->whereNull('to_location_id'), fn ($q) => $q->where('to_location_id', $group->to_location_id))
                ->when($group->created_by === null, fn ($q) => $q->whereNull('created_by'), fn ($q) => $q->where('created_by', $group->created_by))
                ->orderBy('id');

            $rows = $query->get();
            if ($rows->count() < 2) continue;

            $collapsedGroups++;
            $removedRows += $rows->count() - 1;

            $this->line(sprintf(
                '%s — %d rows -> 1 row, quantity %s',
                $group->reason,
                $rows->count(),
                number_format((float) $rows->sum('quantity'), 0),
            ));

            if ($dryRun) continue;

            DB::transaction(function () use ($rows): void {
                $keeper = $rows->first();
                $keeper->quantity = $rows->sum(fn ($row) => (float) $row->quantity);
                $keeper->unit_cost = $rows->firstWhere('unit_cost', '!=', null)?->unit_cost ?? $keeper->unit_cost;
                $keeper->created_at = $rows->min('created_at');
                $keeper->updated_at = $rows->max('updated_at');
                $keeper->saveQuietly();

                InventoryMovement::query()
                    ->whereIn('id', $rows->skip(1)->pluck('id'))
                    ->delete();
            });
        }

        $this->info(($dryRun ? 'Dry run' : 'Compaction') . " complete: {$collapsedGroups} groups, {$removedRows} redundant rows.");

        return self::SUCCESS;
    }
}
