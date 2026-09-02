<?php

namespace App\Services\AI\Ops;

use App\Jobs\GenerateAiOpsInsightsJob;
use App\Models\AiTask;

/**
 * The only web-request-side entry point for AI operations work.
 *
 * It writes one tiny DB row and queues a job. It deliberately does NOT resolve
 * AiGateway, ping Ollama, load a model, generate embeddings, or wait for AI.
 */
class AiOpsDispatcher
{
    public function dispatch(
        string $scope,
        ?int $sourceId = null,
        ?int $triggeredBy = null,
        bool $force = false,
    ): ?AiTask {
        if (! config('ai.ops.enabled', true)) {
            return null;
        }

        $scope = strtolower(trim($scope));
        $taskType = 'ops_' . $scope;

        if (! $force) {
            $existing = AiTask::query()
                ->where('type', $taskType)
                ->whereIn('status', ['pending', 'processing'])
                ->when($sourceId !== null, fn ($q) => $q->where('input->source_id', $sourceId))
                ->when($sourceId === null, fn ($q) => $q->whereNull('taskable_id'))
                ->where('created_at', '>=', now()->subHours(2))
                ->latest()
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        $task = AiTask::create([
            'type' => $taskType,
            'status' => 'pending',
            'taskable_type' => $scope === 'show' && $sourceId ? \App\Models\Show::class : null,
            'taskable_id' => $scope === 'show' ? $sourceId : null,
            'triggered_by' => $triggeredBy,
            'input' => [
                'scope' => $scope,
                'source_id' => $sourceId,
                'background_only' => true,
            ],
        ]);

        GenerateAiOpsInsightsJob::dispatch($task->id, $scope, $sourceId)
            ->onQueue((string) config('ai.ops.queue', 'ai'));

        return $task;
    }
}
