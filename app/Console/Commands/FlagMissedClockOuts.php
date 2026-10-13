<?php

namespace App\Console\Commands;

use App\Services\TimeTrackingService;
use Illuminate\Console\Command;

/** Flag time entries left open past 12 hours and remind the person once. */
class FlagMissedClockOuts extends Command
{
    protected $signature = 'timekeeping:missed-clock-outs';

    protected $description = 'Flag entries open longer than 12 hours for review and notify the person and admins';

    public function handle(TimeTrackingService $time): int
    {
        $count = $time->flagMissedClockOuts();
        $this->info($count.' missed clock-out(s) flagged.');

        return self::SUCCESS;
    }
}
