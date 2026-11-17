<?php

use App\Console\Commands\SyncInventoryLots;
use App\Services\InventoryLotService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * One-time correction: every product's average cost becomes the average of
 * what is actually on hand. Lot quantity the shelves no longer hold is used
 * up oldest first (it was sold before lot tracking caught it), then every
 * average is recomputed. Stock counts are not touched. See inventory:sync-lots.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inventory_lots') || ! Schema::hasTable('inventory_stock')) return;
        $result = SyncInventoryLots::apply(app(InventoryLotService::class));
        logger()->info('inventory:sync-lots (migration)', $result);
    }

    public function down(): void
    {
        // A correction of bookkeeping to match the shelves; nothing to undo.
    }
};
