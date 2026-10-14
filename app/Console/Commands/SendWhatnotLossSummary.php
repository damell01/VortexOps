<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Services\Notifier;
use App\Services\WhatnotLossReport;
use App\Support\BusinessTime;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class SendWhatnotLossSummary extends Command
{
    protected $signature = 'reports:whatnot-loss-summary {--scheduled : Respect configured delivery times} {--period=day : day or week} {--date= : Date in the report period}';
    protected $description = 'Send negative Whatnot estimated earnings through existing notifications';

    public function handle(WhatnotLossReport $report): int
    {
        $local = BusinessTime::local();
        $schedules = json_decode(Setting::get('whatnot_loss_schedules', '{}'), true) ?: [];
        foreach (['day' => 'daily_whatnot_losses', 'week' => 'weekly_whatnot_losses'] as $period => $event) {
            if (!$this->option('scheduled') && $this->option('period') !== $period) continue;
            $schedule = $schedules[$event] ?? ['time' => $period === 'day' ? '18:00' : '17:00', 'day' => 5];
            if ($this->option('scheduled') && ($local->format('H:i') !== $schedule['time'] || ($period === 'week' && $local->dayOfWeek !== (int) $schedule['day']))) continue;
            $anchor = $this->option('date') ? Carbon::parse($this->option('date')) : Carbon::parse($local->toDateString());
            $from = $period === 'week' ? $anchor->copy()->subDays(6) : $anchor->copy();
            $to = $anchor->copy();
            $key = 'whatnot-loss-summary:'.$event.':'.$local->toDateString();
            $lock = Cache::lock($key.':lock', 300);
            if (!$lock->get()) continue;
            try {
                if ($this->option('scheduled') && Cache::has($key)) continue;
                $notification = $report->notification($event, $from, $to);
                if ($notification) {
                    $sent = Notifier::send($event, $notification);
                    $this->info("{$event}: summary routed to {$sent} users.");
                } else $this->info("{$event}: no negative shows; skipped.");
                if ($this->option('scheduled')) Cache::put($key, true, now()->addDays(8));
            } finally { $lock->release(); }
        }
        return self::SUCCESS;
    }
}
