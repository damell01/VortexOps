<?php

namespace App\Console\Commands;

use App\Services\PayRunAutomationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class BackfillPayRuns extends Command
{
    protected $signature = 'payroll:backfill
        {from : First date to inspect}
        {to : Last date to inspect}
        {--member-type= : streamer or fulfillment}
        {--apply : Create/sync missing and Draft Pay Runs; finalized history stays read-only}';

    protected $description = 'Preview historical Pay Runs, or safely create/recalculate Draft weeks using the live calculation path.';

    public function handle(PayRunAutomationService $automation): int
    {
        $from = Carbon::parse($this->argument('from'));
        $to = Carbon::parse($this->argument('to'));
        $memberType = $this->option('member-type') ?: null;

        if ($to->lt($from)) {
            $this->error('The to date must be on or after the from date.');
            return self::INVALID;
        }

        if ($memberType && ! in_array($memberType, ['streamer', 'fulfillment'], true)) {
            $this->error('--member-type must be streamer or fulfillment.');
            return self::INVALID;
        }

        $rows = $automation->previewRange($from, $to, $memberType);
        $this->table(
            ['Week', 'Status', 'Shows', 'Existing', 'Calculated', 'Difference', 'Result'],
            array_map(fn ($row) => [
                $row['week_start'] . ' - ' . $row['week_end'],
                $row['batch_status'] ?? 'Missing',
                $row['shows_found'],
                '$' . number_format($row['existing_amount'], 2),
                '$' . number_format($row['calculated_amount'], 2),
                ($row['difference'] >= 0 ? '+' : '-') . '$' . number_format(abs($row['difference']), 2),
                $row['result'],
            ], $rows),
        );

        if (! $this->option('apply')) {
            $this->newLine();
            $this->info('DRY RUN ONLY — no payroll data was changed. Add --apply to create/sync missing or Draft weeks.');
            return self::SUCCESS;
        }

        if (! $this->confirm('Apply only to missing/Draft Pay Runs? Finalized, submitted and paid weeks will remain untouched.', false)) {
            $this->info('No changes made.');
            return self::SUCCESS;
        }

        foreach ($rows as $row) {
            if ($row['read_only']) {
                $this->line("Skipping {$row['week_start']} — {$row['batch_status']} is historical and read-only.");
                continue;
            }

            $result = $automation->syncWeek($row['week_start']);
            $this->info("Synced {$row['week_start']} — $" . number_format((float) $result['batch']->total_payout, 2));
        }

        return self::SUCCESS;
    }
}
