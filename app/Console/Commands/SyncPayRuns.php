<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Services\PayRunAutomationService;
use Illuminate\Console\Command;

class SyncPayRuns extends Command
{
    protected $signature = 'payroll:sync-pay-runs {--week= : Any date in the Monday-Sunday week to sync} {--force : Run even when automation is disabled}';

    protected $description = 'Ensure the weekly Draft Pay Run exists and optionally refresh eligible show payouts.';

    public function handle(PayRunAutomationService $automation): int
    {
        if (! $this->option('force') && ! Setting::getBool('payroll_auto_setup_enabled', false)) {
            $this->info('Automatic Pay Run setup is disabled. Use --force to run it manually.');
            return self::SUCCESS;
        }

        $recalculate = $this->option('force') || Setting::getBool('payroll_auto_recalculate_drafts', true);

        try {
            // One run per business once people are in businesses.
            $results = $automation->syncAllForWeek($this->option('week') ?: now(), $recalculate, scheduled: true);
        } catch (\Throwable $e) {
            Setting::set('payroll_last_automation_error', $e->getMessage());
            report($e);
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->line('Draft recalculation: ' . ($recalculate ? 'enabled' : 'disabled'));
        foreach ($results as $result) {
            $batch = $result['batch'];
            $this->info(($result['created'] ? 'Created' : 'Updated') . " Pay Run #{$batch->id} ({$batch->businessLabel()}): {$batch->week_start->toDateString()} - {$batch->week_end->toDateString()}");
            $this->line('Shows scanned: ' . $result['shows_scanned']);
            $this->line('New payouts attached: ' . $result['payouts_attached']);
            $this->line('Total: $' . number_format((float) $batch->total_payout, 2));

            foreach ($result['warnings'] as $warning) {
                $this->warn($warning);
            }
        }

        return self::SUCCESS;
    }
}
