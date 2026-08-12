<?php

namespace App\AI\Services;

use App\AI\Contracts\AIProvider;
use App\AI\DTOs\AiMessage;
use App\AI\DTOs\AiResponse;
use App\AI\Enums\AiTask;
use App\AI\Events\AiCallCompleted;
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

            return $this->record(new AiResponse(
                content:   $content,
                model:     $model,
                task:      $task,
                provider:  $this->provider->name(),
                latencyMs: $this->elapsed($start),
            ));
        } catch (\Throwable $e) {
            Log::error('AiGateway::chat failed', ['task' => $task->value, 'model' => $model, 'error' => $e->getMessage()]);

            return $this->record(AiResponse::failed($task, $model, $this->provider->name(), $e->getMessage(), $this->elapsed($start)));
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
        $model = $this->router->modelFor($task);
        $inner = $this->provider->stream(
            AiMessage::normalizeMany($messages),
            $model,
            $this->router->generationOptions($task, $overrides),
        );

        return $this->recordStream($task, $model, $inner);
    }

    /**
     * Wrap a provider stream so telemetry is emitted once it finishes (or on
     * mid-stream failure) — otherwise streamed calls, which is the assistant's
     * main path, would go unmonitored. Latency is measured across the whole
     * stream; the assembled text is the recorded content.
     *
     * @param  \Generator<int,string,void,void> $inner
     * @return \Generator<int,string,void,void>
     */
    private function recordStream(AiTask $task, string $model, \Generator $inner): \Generator
    {
        $start    = microtime(true);
        $provider = $this->provider->name();
        $full     = '';

        try {
            foreach ($inner as $chunk) {
                $full .= $chunk;
                yield $chunk;
            }
        } catch (\Throwable $e) {
            Log::error('AiGateway::stream failed', ['task' => $task->value, 'model' => $model, 'error' => $e->getMessage()]);
            $this->record(AiResponse::failed($task, $model, $provider, $e->getMessage(), $this->elapsed($start)));
            throw $e;
        }

        $this->record(new AiResponse($full, $model, $task, $provider, $this->elapsed($start)));
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

            return $this->record(new AiResponse($content, $model, AiTask::Vision, $this->provider->name(), $this->elapsed($start)));
        } catch (\Throwable $e) {
            Log::error('AiGateway::vision failed', ['model' => $model, 'error' => $e->getMessage()]);

            return $this->record(AiResponse::failed(AiTask::Vision, $model, $this->provider->name(), $e->getMessage(), $this->elapsed($start)));
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

    /**
     * Emit telemetry for a completed call, then hand the response back. Firing an
     * event keeps the gateway ignorant of how (or whether) calls are recorded.
     */
    private function record(AiResponse $response): AiResponse
    {
        event(new AiCallCompleted($response));

        return $response;
    }
}
