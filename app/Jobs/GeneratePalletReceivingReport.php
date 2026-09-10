<?php

namespace App\Jobs;

use App\Models\Pallet;
use App\Services\ReceivingReportService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;

class GeneratePalletReceivingReport implements ShouldQueue, ShouldBeUnique
{
    use Queueable, InteractsWithQueue;

    public int $timeout = 120;
    public int $tries = 2;
    public int $uniqueFor = 60;

    public function __construct(public readonly int $palletId) {}

    public function uniqueId(): string
    {
        return 'pallet-receiving-report-' . $this->palletId;
    }

    public function handle(ReceivingReportService $reports): void
    {
        $pallet = Pallet::find($this->palletId);

        if (! $pallet || ! in_array($pallet->status, ['received', 'processed'], true)) {
            return;
        }

        // generatePalletReport() is versioned and idempotent: if the exact
        // current report already exists this returns immediately, otherwise it
        // renders it once so later download clicks only serve a finished file.
        $reports->generatePalletReport($pallet);
    }
}
