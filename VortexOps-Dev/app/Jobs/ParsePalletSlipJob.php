<?php

namespace App\Jobs;

use App\Models\AiTask;
use App\Services\AI\Documents\PalletSlipParser;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ParsePalletSlipJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;
    public int $tries   = 1;

    public function __construct(
        public readonly int    $palletId,
        public readonly int    $aiTaskId,
        public readonly string $storedPath,
    ) {}

    public function handle(): void
    {
        $task = AiTask::findOrFail($this->aiTaskId);
        $task->markProcessing();

        try {
            $lines = app(PalletSlipParser::class)->parse($this->storedPath);
            $task->markCompleted(['lines' => $lines]);
        } catch (\Throwable $e) {
            Log::error('ParsePalletSlipJob failed', ['error' => $e->getMessage(), 'task' => $this->aiTaskId]);
            $task->markFailed($e->getMessage());
        }
    }

    public function failed(\Throwable $e): void
    {
        @unlink($this->storedPath);
        Log::error('ParsePalletSlipJob timed out or failed fatally', [
            'ai_task_id' => $this->aiTaskId,
            'error'      => $e->getMessage(),
        ]);
        optional(AiTask::find($this->aiTaskId))->markFailed($e->getMessage());
    }
}
