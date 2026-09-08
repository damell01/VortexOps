<?php

namespace App\Console\Commands;

use App\Models\Show;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TrimWhatnotReportingHistory extends Command
{
    protected $signature = 'whatnot:trim-reporting-history
        {--before=2026-07-01 : Delete Whatnot show history before this date}
        {--apply : Actually delete the data; without this the command is preview-only}
        {--skip-backup : Skip the automatic database backup before deleting}';

    protected $description = 'Remove Whatnot show/reporting history before the reporting start date';

    public function handle(): int
    {
        try {
            $before = Carbon::parse((string) $this->option('before'))->startOfDay();
        } catch (\Throwable) {
            $this->error('Invalid --before date. Use YYYY-MM-DD.');
            return self::FAILURE;
        }

        if ($before->isFuture()) {
            $this->error('--before cannot be in the future.');
            return self::FAILURE;
        }

        $query = Show::query()
            ->whereNotNull('whatnot_channel_id')
            ->whereDate('show_date', '<', $before->toDateString());

        $count = (clone $query)->count();
        $gross = (float) (clone $query)->sum('gross_revenue');
        $first = (clone $query)->min('show_date');
        $last = (clone $query)->max('show_date');

        $this->info('WHATNOT REPORTING HISTORY TRIM');
        $this->line('Cutoff: delete shows before ' . $before->toDateString());
        $this->line("Shows matched: {$count}");
        $this->line('Matched gross: $' . number_format($gross, 2));
        $this->line('Date range: ' . ($first ?: '—') . ' through ' . ($last ?: '—'));

        if ($count === 0) {
            $this->info('Nothing to remove.');
            return self::SUCCESS;
        }

        if (! $this->option('apply')) {
            $this->warn('Preview only. Nothing was deleted. Re-run with --apply when you are ready.');
            return self::SUCCESS;
        }

        if (! $this->option('skip-backup')) {
            $this->info('Creating a database backup first…');
            $backupExit = $this->call('db:backup');
            if ($backupExit !== self::SUCCESS) {
                $this->error('Backup failed. No history was deleted.');
                return self::FAILURE;
            }
        }

        try {
            DB::transaction(function () use ($query) {
                $query->orderBy('id')->chunkById(100, function ($shows) {
                    foreach ($shows as $show) {
                        $show->delete();
                    }
                });
            });
        } catch (\Throwable $e) {
            report($e);
            $this->error('Delete failed and was rolled back: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->info("Removed {$count} Whatnot show(s) before {$before->toDateString()} and their database-cascaded show data.");
        $this->line('Reporting can now be treated as beginning on ' . $before->toDateString() . '.');

        return self::SUCCESS;
    }
}
