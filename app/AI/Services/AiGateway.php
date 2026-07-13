<?php

namespace App\AI\Services;

use App\AI\Contracts\AIProvider;
use App\AI\DTOs\AiMessage;
use App\AI\DTOs\AiResponse;
use App\AI\Enums\AiTask;
use Illuminate\Support\Facades\Log;

/**
 * The one entry point every feature uses to talk to AI. It picks the model and
 * generation settings for the task (ModelRouter), runs the call through the
 * active provider, and returns a metadata-rich AiResponse. Filament pages,
 * Livewire components, jobs, and services depend on THIS — never on a provider
 * or the raw OllamaClient — so the backend and per-task tuning stay swappable
 * from one place.
 */
final class AiGateway
{
    public function __construct(
        private readonly AIProvider  $provider,
        private readonly ModelRouter $router,
    ) {}

    /**
     * Run a chat completion for a task.
     *
     * @param  array<int, AiMessage|array{role:string,content:string}> $messages
     * @param  array<string,mixed> $overrides
     */
    public function chat(AiTask $task, array $messages, array $overrides = []): AiResponse
    {
        $model = $this->router->modelFor($task);
        $start = microtime(true);

        try {
            $content = $this->provider->chat(
                AiMessage::normalizeMany($messages),
                $model,
                $this->router->generationOptions($task, $overrides),
            );

            return new AiResponse(
                content:   $content,
                model:     $model,
                task:      $task,
                provider:  $this->provider->name(),
                latencyMs: $this->elapsed($start),
            );
        } catch (\Throwable $e) {
            Log::error('AiGateway::chat failed', ['task' => $task->value, 'model' => $model, 'error' => $e->getMessage()]);

            return AiResponse::failed($task, $model, $this->provider->name(), $e->getMessage(), $this->elapsed($start));
        }
    }

    /**
     * Chat that must return JSON. Forces the Json task's json format and decodes
     * the result. Returns null on transport failure or unparseable output.
     *
     * @param  array<int, AiMessage|array{role:string,content:string}> $messages
     * @param  array<string,mixed> $overrides
     * @return array<mixed>|null
     */
    public function json(array $messages, array $overrides = []): ?array
    {
        $response = $this->chat(AiTask::Json, $messages, $overrides);

        return $response->success ? $response->json() : null;
    }

    /**
     * Streaming chat — yields content chunks. Callers own the metadata (latency,
     * success) since a generator can't return a DTO.
     *
     * @param  array<int, AiMessage|array{role:string,content:string}> $messages
     * @param  array<string,mixed> $overrides
     * @return \Generator<int,string,void,void>
     */
    public function stream(AiTask $task, array $messages, array $overrides = []): \Generator
    {
        return $this->provider->stream(
            AiMessage::normalizeMany($messages),
            $this->router->modelFor($task),
            $this->router->generationOptions($task, $overrides),
        );
    }

    /**
     * Vision completion — a prompt plus one base64 image.
     *
     * @param array<string,mixed> $overrides
     */
    public function vision(string $prompt, string $base64Image, array $overrides = []): AiResponse
    {
        $model = $this->router->modelFor(AiTask::Vision);
        $start = microtime(true);

        try {
            $content = $this->provider->vision($prompt, $base64Image, $model, $overrides);

            return new AiResponse($content, $model, AiTask::Vision, $this->provider->name(), $this->elapsed($start));
        } catch (\Throwable $e) {
            Log::error('AiGateway::vision failed', ['model' => $model, 'error' => $e->getMessage()]);

            return AiResponse::failed(AiTask::Vision, $model, $this->provider->name(), $e->getMessage(), $this->elapsed($start));
        }
    }

    /**
     * Embed text with the embedding model. Null when the backend is unavailable.
     *
     * @return float[]|null
     */
    public function embed(string $text): ?array
    {
        return $this->provider->embed($text, $this->router->modelFor(AiTask::Embedding));
    }

    public function isHealthy(): bool
    {
        return $this->provider->isHealthy();
    }

    public function provider(): AIProvider
    {
        return $this->provider;
    }

    public function router(): ModelRouter
    {
        return $this->router;
    }

    private function elapsed(float $start): int
    {
        return (int) ((microtime(true) - $start) * 1000);
    }
}
