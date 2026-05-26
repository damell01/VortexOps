<?php

namespace App\Jobs;

use App\Models\AiLog;
use App\Services\OllamaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class RunAiQuery implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 1;
    public int $timeout = 240;

    public function __construct(
        public readonly string $pendingKey,
        public readonly string $question,
        public readonly string $systemText,
        public readonly ?int   $userId,
    ) {}

    public function handle(OllamaService $service): void
    {
        $log = $service->query($this->question, $this->systemText, $this->userId);

        Cache::put("ai_pending_{$this->pendingKey}", [
            'content' => $log->response ?: '(empty response)',
            'success' => $log->success,
            'error'   => $log->error_message,
            'latency' => $log->latency_ms,
            'time'    => now()->format('g:i A'),
        ], 300);
    }

    public function failed(\Throwable $e): void
    {
        Cache::put("ai_pending_{$this->pendingKey}", [
            'content' => null,
            'success' => false,
            'error'   => 'Job failed: ' . $e->getMessage(),
            'latency' => 0,
            'time'    => now()->format('g:i A'),
        ], 300);
    }
}
