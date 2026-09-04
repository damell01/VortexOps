<?php

namespace App\Console\Commands;

use App\Models\Shipment;
use Illuminate\Console\Command;

class RepairWhatnotShipmentFields extends Command
{
    protected $signature = 'whatnot:repair-shipment-fields {--dry-run : Show what would change without saving}';

    protected $description = 'Repair recipient, item count, date, weight, dimensions, carrier and status from stored Whatnot shipment raw payloads';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $checked = 0;
        $changed = 0;

        Shipment::query()
            ->whereNotNull('raw_payload')
            ->orderBy('id')
            ->chunkById(250, function ($shipments) use ($dryRun, &$checked, &$changed): void {
                foreach ($shipments as $shipment) {
                    $checked++;
                    $shipment->normalizeScrapedPayload();

                    if (! $shipment->isDirty()) {
                        continue;
                    }

                    $changed++;

                    if (! $dryRun) {
                        $shipment->saveQuietly();
                    }
                }

                $this->line("Checked {$checked}; " . ($dryRun ? 'would repair' : 'repaired') . " {$changed}.");
            });

        $this->info(($dryRun ? 'Dry run complete' : 'Shipment repair complete') . ": {$checked} checked, {$changed} changed.");

        return self::SUCCESS;
    }
}
