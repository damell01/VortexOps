<?php

namespace App\Console\Commands;

use App\Models\Pallet;
use App\Services\ReceivingReportService;
use Illuminate\Console\Command;

class WarmPalletReceivingReports extends Command
{
    protected $signature = 'reports:warm-pallet-receipts {--force : Re-render current report versions even if already cached}';

    protected $description = 'Pre-generate receiving PDFs for completed pallets so downloads are instant.';

    public function handle(ReceivingReportService $reports): int
    {
        $query = Pallet::query()
            ->whereIn('status', ['received', 'processed'])
            ->orderBy('id');

        $count = (clone $query)->count();
        if ($count === 0) {
            $this->info('No completed pallets need reports.');
            return self::SUCCESS;
        }

        $this->info("Preparing {$count} pallet receiving report(s)...");
        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $failed = 0;
        $query->chunkById(25, function ($pallets) use ($reports, $bar, &$failed) {
            foreach ($pallets as $pallet) {
                try {
                    if ($this->option('force')) {
                        $reports->forgetPreparedPalletReport($pallet);
                    }
                    $reports->generatePalletReport($pallet);
                } catch (\Throwable $e) {
                    $failed++;
                    report($e);
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        if ($failed > 0) {
            $this->warn("Prepared " . ($count - $failed) . " report(s); {$failed} failed. Check the application log.");
            return self::FAILURE;
        }

        $this->info("All {$count} receiving reports are ready for immediate download.");
        return self::SUCCESS;
    }
}
