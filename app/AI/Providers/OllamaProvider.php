<?php

namespace App\AI\Providers;

use App\AI\Contracts\AIProvider;
use App\AI\Contracts\PullsModels;
use App\Services\AI\OllamaClient;

/**
 * Ollama-backed provider. Thin adapter over the existing OllamaClient so all
 * HTTP still flows through that single abstraction — this class only translates
 * the normalised option set into Ollama's wire shape.
 */
final class OllamaProvider implements AIProvider, PullsModels
{
    public function __construct(
        private readonly OllamaClient $client,
    ) {}

    public function name(): string
    {
        return 'ollama';
    }

    public function chat(array $messages, string $model, array $options = []): string
    {
        return $this->client->chat($messages, $this->translate($model, $options));
    }

    public function stream(array $messages, string $model, array $options = []): \Generator
    {
        return $this->client->chatStream($messages, $this->translate($model, $options));
    }

    public function vision(string $prompt, string $base64Image, string $model, array $options = []): string
    {
        return $this->client->vision($prompt, $base64Image, [
            'model'   => $model,
            'timeout' => $options['timeout'] ?? 240,
        ]);
    }

    public function embed(string $text, string $model): ?array
    {
        return $this->client->embed($text, ['model' => $model]);
    }

    public function listModels(): array
    {
        return $this->client->listModels();
    }

    public function isHealthy(): bool
    {
        return $this->client->isOnline();
    }

    public function pull(string $model, ?callable $onProgress = null): bool
    {
        return $this->client->pull($model, $onProgress);
    }

    /**
     * Map the normalised option set (temperature, top_p, max_tokens,
     * context_length, timeout, format) onto what OllamaClient expects.
     *
     * @param  array<string,mixed> $options
     * @return array<string,mixed>
     */
    private function translate(string $model, array $options): array
    {
        $ollamaOptions = array_filter([
            'temperature' => $options['temperature']    ?? null,
            'top_p'       => $options['top_p']           ?? null,
            'num_predict' => $options['max_tokens']      ?? null,
            'num_ctx'     => $options['context_length']  ?? null,
        ], fn ($v) => $v !== null);

        return array_filter([
            'model'          => $model,
            'timeout'        => $options['timeout'] ?? null,
            'format'         => $options['format'] ?? null,
            'ollama_options' => $ollamaOptions !== [] ? $ollamaOptions : null,
        ], fn ($v) => $v !== null);
    }
}
