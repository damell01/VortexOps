<?php

namespace App\Console\Commands;

use App\Jobs\ParsePalletSlipJob;
use App\Models\AiTask;
use App\Models\Pallet;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class RetryFailedManifestJobs extends Command
{
    protected $signature = 'ai:retry-failed-manifests
        {task? : Specific failed AiTask ID to retry}
        {--all : Retry every failed manifest task that still has its retained source file}';

    protected $description = 'Retry failed AI pallet-manifest jobs using their retained original upload';

    public function handle(): int
    {
        $taskId = $this->argument('task');

        if (! $taskId && ! $this->option('all')) {
            $this->error('Pass a failed task ID, or use --all.');
            return self::FAILURE;
        }

        $query = AiTask::query()
            ->where('type', 'parse_pallet_slip')
            ->where('status', 'failed');

        if ($taskId) {
            $query->whereKey((int) $taskId);
        }

        $tasks = $query->orderBy('id')->get();
        if ($tasks->isEmpty()) {
            $this->info('No matching failed manifest jobs found.');
            return self::SUCCESS;
        }

        $queued = 0;
        $skipped = 0;

        foreach ($tasks as $task) {
            $input = is_array($task->input) ? $task->input : [];
            $sourceRelative = trim((string) ($input['stored_path'] ?? ''));
            $palletId = (int) ($input['pallet_id'] ?? $task->taskable_id ?? 0);

            if ($palletId < 1 || ! Pallet::query()->whereKey($palletId)->exists()) {
                $this->warn("Task #{$task->id}: pallet no longer exists; skipped.");
                $skipped++;
                continue;
            }

            if ($sourceRelative === '' || ! Storage::disk('local')->exists($sourceRelative)) {
                $this->warn("Task #{$task->id}: retained source file is unavailable; skipped.");
                $skipped++;
                continue;
            }

            $extension = strtolower((string) ($input['extension'] ?? pathinfo($sourceRelative, PATHINFO_EXTENSION)));
            $filename = 'manual_retry_task_' . $task->id . '_' . uniqid() . ($extension ? '.' . $extension : '');
            $processingRelative = 'manifest-processing/' . $filename;
            Storage::disk('local')->makeDirectory('manifest-processing');

            if (! Storage::disk('local')->copy($sourceRelative, $processingRelative)) {
                $this->warn("Task #{$task->id}: could not create processing copy; skipped.");
                $skipped++;
                continue;
            }

            $task->update([
                'status' => 'pending',
                'error_message' => null,
                'started_at' => null,
                'completed_at' => null,
                'output' => null,
                'input' => array_merge($input, [
                    'processing_path' => $processingRelative,
                    'retry_requested_at' => now()->toIso8601String(),
                ]),
            ]);

            ParsePalletSlipJob::dispatch(
                $palletId,
                $task->id,
                Storage::disk('local')->path($processingRelative),
            )->onQueue('ai');

            $this->info("Task #{$task->id}: queued for retry.");
            $queued++;
        }

        $this->newLine();
        $this->info("Queued: {$queued} · Skipped: {$skipped}");

        return $queued > 0 ? self::SUCCESS : self::FAILURE;
    }
}
