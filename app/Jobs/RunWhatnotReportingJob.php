<?php

namespace App\Jobs;

use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;

class RunWhatnotReportingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public int $tries = 1;
    public int $timeout = 14400;

    public function __construct(public readonly string $mode, public readonly int $launchedBy)
    {
        $this->onQueue('whatnot');
    }

    public function handle(): void
    {
        $args = match ($this->mode) {
            'test' => ['--test' => true, '--skip-if-busy' => true],
            'freshness' => ['--since' => now()->subDays(7)->toDateString(), '--show-limit' => 10, '--order-batch' => 10, '--analytics-limit' => 5, '--shipment-batch' => 10, '--max-runtime' => 2700, '--skip-if-busy' => true],
            'full' => ['--since' => '2026-07-01', '--show-limit' => 30, '--order-batch' => 30, '--analytics-limit' => 25, '--shipment-batch' => 30, '--max-runtime' => 10800, '--skip-if-busy' => true],
            default => throw new \InvalidArgumentException("Unknown Whatnot reporting mode: {$this->mode}"),
        };
        Setting::set('whatnot_ui_job', json_encode(['mode'=>$this->mode,'status'=>'running','launched_by'=>$this->launchedBy,'started_at'=>now()->toIso8601String(),'phase'=>'Starting coordinated Scrapling pipeline']));
        try {
            $code = Artisan::call('whatnot:sync-reporting', $args);
            $output = trim(Artisan::output());
            $state = json_decode(Setting::get('whatnot_ui_job', '{}'), true) ?: [];
            Setting::set('whatnot_ui_job', json_encode(array_merge($state, ['status'=>$code===0?'completed':'failed','finished_at'=>now()->toIso8601String(),'phase'=>$code===0?'Pipeline completed successfully':'Pipeline failed','output'=>$output !== '' ? mb_substr($output,-12000) : 'Command completed without captured console output.'])));
            if ($code !== 0) throw new \RuntimeException("Whatnot reporting exited with code {$code}");
        } catch (\Throwable $e) {
            $state = json_decode(Setting::get('whatnot_ui_job', '{}'), true) ?: [];
            Setting::set('whatnot_ui_job', json_encode(array_merge($state, ['status'=>'failed','finished_at'=>now()->toIso8601String(),'error'=>$e->getMessage()])));
            throw $e;
        }
    }
}
