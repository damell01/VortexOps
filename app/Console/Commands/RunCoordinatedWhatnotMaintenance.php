<?php

namespace App\Console\Commands;

use App\Models\ShowIngestionLog;
use App\Support\WhatnotPipelineLock;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class RunCoordinatedWhatnotMaintenance extends Command
{
    protected $signature = 'whatnot:run-maintenance
        {task : nightly or deep}
        {--skip-if-busy : Skip cleanly when another Whatnot pipeline is active}';

    protected $description = 'Run long Whatnot reconciliation/backfill work through the shared pipeline coordinator';

    public function handle(): int
    {
        $task = strtolower((string) $this->argument('task'));

        $config = match ($task) {
            'nightly' => [
                'label' => 'Nightly Reconciliation',
                'source' => 'whatnot_nightly_reconciliation',
                'command' => 'whatnot:sync',
                'arguments' => ['--type' => 'last_30_days'],
                'payload' => ['window' => 'last_30_days'],
            ],
            'deep' => [
                'label' => 'Deep Backfill',
                'source' => 'whatnot_deep_backfill',
                'command' => 'whatnot:import-ledger',
                'arguments' => ['--days' => 1825],
                'payload' => ['days' => 1825],
            ],
            default => null,
        };

        if (! $config) {
            $this->error('Task must be nightly or deep.');
            return self::FAILURE;
        }

        $skipIfBusy = (bool) $this->option('skip-if-busy');
        $lock = WhatnotPipelineLock::acquire($config['label'], $skipIfBusy ? 0 : 14400);

        if (! $lock) {
            $message = WhatnotPipelineLock::busyMessage();
            if ($skipIfBusy) {
                $this->line("{$config['label']}: skipped — {$message}");
                Log::info('Whatnot maintenance skipped because another pipeline is active', [
                    'task' => $task,
                    'reason' => $message,
                ]);
                return self::SUCCESS;
            }

            $this->error("{$config['label']}: timed out waiting for the Whatnot pipeline coordinator. {$message}");
            return self::FAILURE;
        }

        try {
            $this->line("{$config['label']}: starting");
            $exit = Artisan::call($config['command'], $config['arguments']);
            $output = trim(Artisan::output());
            if ($output !== '') {
                $this->output->write($output . PHP_EOL);
            }

            $status = $exit === 0 ? 'success' : 'failed';
            ShowIngestionLog::create([
                'source' => $config['source'],
                'status' => $status,
                'raw_payload' => $config['payload'] + ['exit_code' => $exit],
                'error_message' => $exit === 0 ? null : "{$config['label']} exited with code {$exit}.",
            ]);

            return $exit === 0 ? self::SUCCESS : self::FAILURE;
        } catch (\Throwable $e) {
            ShowIngestionLog::create([
                'source' => $config['source'],
                'status' => 'failed',
                'raw_payload' => $config['payload'],
                'error_message' => $e->getMessage(),
            ]);

            Log::error('Whatnot coordinated maintenance failed', [
                'task' => $task,
                'exception' => $e->getMessage(),
            ]);

            $this->error($e->getMessage());
            return self::FAILURE;
        } finally {
            WhatnotPipelineLock::release($lock);
        }
    }
}
