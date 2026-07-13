<?php

namespace App\Services\AI;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\StreamInterface;

/**
 * Single HTTP abstraction for all Ollama API calls.
 * Reads URL/model from Settings (with env/config fallback) so the admin
 * panel's Ollama settings always take precedence at runtime.
 */
class OllamaClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $defaultModel,
        private readonly string $embeddingModel,
        private readonly string $visionModel,
    ) {}

    public static function fromSettings(): static
    {
        $baseUrl = rtrim(Setting::get('ollama_base_url') ?: config('services.ollama.url', 'http://localhost:11434'), '/');

        // Specific model keys take priority; fall back to the legacy generic key
        $defaultModel   = Setting::get('ollama_chat_model')
            ?: Setting::get('ollama_model')
            ?: config('services.ollama.model', 'llama3.2:3b');
        $embeddingModel = Setting::get('ollama_embedding_model')
            ?: config('services.ollama.embedding_model', 'nomic-embed-text');
        $visionModel    = Setting::get('ollama_vision_model')
            ?: config('services.ollama.vision_model', 'moondream');

        return new static($baseUrl, $defaultModel, $embeddingModel, $visionModel);
    }

    // ── Text generation ────────────────────────────────────────────────────────

    /**
     * Generate a completion (non-streaming). Returns the response text.
     */
    public function generate(string $prompt, array $options = []): string
    {
        $response = Http::timeout($options['timeout'] ?? 60)
            ->post("{$this->baseUrl}/api/generate", array_filter([
                'model'  => $options['model'] ?? $this->defaultModel,
                'prompt' => $prompt,
                'stream' => false,
                'format' => $options['format'] ?? null,
            ]));

        if (! $response->successful()) {
            throw new \RuntimeException("Ollama generate HTTP {$response->status()}: {$response->body()}");
        }

        return $response->json('response', '');
    }

    /**
     * Chat completion (non-streaming).
     *
     * @param list<array{role:string,content:string}> $messages
     */
    public function chat(array $messages, array $options = []): string
    {
        $response = Http::timeout($options['timeout'] ?? 120)
            ->post("{$this->baseUrl}/api/chat", array_filter([
                'model'   => $options['model'] ?? $this->defaultModel,
                'messages'=> $messages,
                'stream'  => false,
                'format'  => $options['format'] ?? null,
                'options' => $options['ollama_options'] ?? null,
            ]));

        if (! $response->successful()) {
            throw new \RuntimeException("Ollama chat HTTP {$response->status()}: {$response->body()}");
        }

        return $response->json('message.content', '');
    }

    /**
     * Streaming chat — yields content string chunks.
     *
     * @param list<array{role:string,content:string}> $messages
     * @return \Generator<int,string,void,void>
     */
    public function chatStream(array $messages, array $options = []): \Generator
    {
        $response = Http::withOptions(['stream' => true])
            ->timeout($options['timeout'] ?? 120)
            ->post("{$this->baseUrl}/api/chat", array_filter([
                'model'   => $options['model'] ?? $this->defaultModel,
                'messages'=> $messages,
                'stream'  => true,
                'options' => $options['ollama_options'] ?? null,
            ]));

        if (! $response->successful()) {
            throw new \RuntimeException("Ollama stream HTTP {$response->status()}");
        }

        $body = $response->getBody();

        while (! $body->eof()) {
            $line = $this->readLine($body);

            if ($line === '') {
                continue;
            }

            $data  = json_decode($line, true);
            $chunk = $data['message']['content'] ?? '';

            if ($chunk !== '') {
                yield $chunk;
            }

            if ($data['done'] ?? false) {
                break;
            }
        }
    }

    /**
     * Vision completion — send a base64 image with a prompt.
     */
    public function vision(string $prompt, string $base64Image, array $options = []): string
    {
        $model = $options['model'] ?? $this->visionModel;

        $response = Http::timeout($options['timeout'] ?? 240)
            ->post("{$this->baseUrl}/api/generate", [
                'model'  => $model,
                'prompt' => $prompt,
                'images' => [$base64Image],
                'stream' => false,
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException("Ollama vision HTTP {$response->status()}. Is '{$model}' pulled?");
        }

        return $response->json('response', '');
    }

    // ── Embeddings ─────────────────────────────────────────────────────────────

    /**
     * Generate an embedding vector. Returns null when Ollama is unavailable.
     *
     * @return float[]|null
     */
    public function embed(string $text, array $options = []): ?array
    {
        try {
            $response = Http::timeout(10)->post("{$this->baseUrl}/api/embeddings", [
                'model'  => $options['model'] ?? $this->embeddingModel,
                'prompt' => $text,
            ]);

            if ($response->successful()) {
                return $response->json('embedding');
            }
        } catch (\Throwable $e) {
            Log::warning("OllamaClient::embed — {$e->getMessage()}");
        }

        return null;
    }

    // ── Health / metadata ──────────────────────────────────────────────────────

    /**
     * @return string[]
     */
    public function listModels(): array
    {
        try {
            $response = Http::timeout(3)->get("{$this->baseUrl}/api/tags");
            if ($response->successful()) {
                return collect($response->json('models', []))->pluck('name')->all();
            }
        } catch (\Throwable) {}

        return [];
    }

    public function isOnline(): bool
    {
        try {
            return Http::timeout(3)->get("{$this->baseUrl}/api/tags")->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    public function getDefaultModel(): string
    {
        return $this->defaultModel;
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function readLine(StreamInterface $stream): string
    {
        $line = '';
        while (! $stream->eof()) {
            $char = $stream->read(1);
            if ($char === "\n") {
                break;
            }
            $line .= $char;
        }
        return trim($line);
    }
}
