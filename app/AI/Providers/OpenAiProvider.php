<?php

namespace App\AI\Providers;

use App\AI\Contracts\AIProvider;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\StreamInterface;

/**
 * OpenAI-compatible provider. Speaks the /chat/completions + /embeddings + /models
 * API, so beyond OpenAI itself it drives DeepSeek, Qwen/DashScope, Together,
 * Groq, and local gateways — point base_url at the endpoint. Implemented behind
 * the same AIProvider seam as Ollama, so nothing above the provider changes.
 */
final class OpenAiProvider implements AIProvider
{
    public function __construct(
        private readonly string  $baseUrl,
        private readonly ?string $apiKey,
    ) {}

    public function name(): string
    {
        return 'openai';
    }

    public function chat(array $messages, string $model, array $options = []): string
    {
        $response = $this->http($options['timeout'] ?? 120)
            ->post('/chat/completions', $this->payload($messages, $model, $options));

        $this->assertOk($response, 'chat');

        return (string) $response->json('choices.0.message.content', '');
    }

    public function stream(array $messages, string $model, array $options = []): \Generator
    {
        $response = $this->http($options['timeout'] ?? 120)
            ->withOptions(['stream' => true])
            ->post('/chat/completions', $this->payload($messages, $model, $options, stream: true));

        $this->assertOk($response, 'stream');

        $body = $response->getBody();

        while (! $body->eof()) {
            $line = $this->readLine($body);

            // OpenAI SSE frames are "data: {json}"; the stream ends with [DONE].
            if ($line === '' || ! str_starts_with($line, 'data:')) {
                continue;
            }

            $payload = trim(substr($line, 5));
            if ($payload === '' || $payload === '[DONE]') {
                if ($payload === '[DONE]') {
                    break;
                }
                continue;
            }

            $chunk = json_decode($payload, true)['choices'][0]['delta']['content'] ?? '';
            if ($chunk !== '') {
                yield $chunk;
            }
        }
    }

    public function vision(string $prompt, string $base64Image, string $model, array $options = []): string
    {
        // Vision goes through chat completions with an image content part.
        $messages = [[
            'role'    => 'user',
            'content' => $prompt,
            'images'  => [$base64Image],
        ]];

        return $this->chat($messages, $model, $options + ['timeout' => 240]);
    }

    public function embed(string $text, string $model): ?array
    {
        try {
            $response = $this->http(15)->post('/embeddings', [
                'model' => $model,
                'input' => $text,
            ]);

            if ($response->successful()) {
                return $response->json('data.0.embedding');
            }
        } catch (\Throwable $e) {
            Log::warning("OpenAiProvider::embed — {$e->getMessage()}");
        }

        return null;
    }

    public function listModels(): array
    {
        try {
            $response = $this->http(5)->get('/models');
            if ($response->successful()) {
                return collect($response->json('data', []))->pluck('id')->filter()->values()->all();
            }
        } catch (\Throwable) {
        }

        return [];
    }

    public function isHealthy(): bool
    {
        try {
            return $this->http(5)->get('/models')->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function http(int $timeout): PendingRequest
    {
        $request = Http::baseUrl($this->baseUrl)->timeout($timeout)->acceptJson();

        return $this->apiKey ? $request->withToken($this->apiKey) : $request;
    }

    /**
     * @param  list<array{role:string,content:string,images?:string[]}> $messages
     * @param  array<string,mixed> $options
     * @return array<string,mixed>
     */
    private function payload(array $messages, string $model, array $options, bool $stream = false): array
    {
        return array_filter([
            'model'           => $model,
            'messages'        => array_map([$this, 'toApiMessage'], $messages),
            'temperature'     => $options['temperature'] ?? null,
            'top_p'           => $options['top_p'] ?? null,
            'max_tokens'      => $options['max_tokens'] ?? null,
            'stream'          => $stream ?: null,
            'response_format' => ($options['format'] ?? null) === 'json' ? ['type' => 'json_object'] : null,
        ], fn ($v) => $v !== null);
    }

    /**
     * Convert an internal message to OpenAI shape. A message carrying images
     * becomes multimodal content parts (text + base64 data URIs).
     *
     * @param  array{role:string,content:string,images?:string[]} $message
     * @return array{role:string,content:mixed}
     */
    private function toApiMessage(array $message): array
    {
        if (empty($message['images'])) {
            return ['role' => $message['role'], 'content' => $message['content']];
        }

        $parts = [['type' => 'text', 'text' => $message['content']]];
        foreach ($message['images'] as $b64) {
            $parts[] = ['type' => 'image_url', 'image_url' => ['url' => "data:image/jpeg;base64,{$b64}"]];
        }

        return ['role' => $message['role'], 'content' => $parts];
    }

    private function assertOk(\Illuminate\Http\Client\Response $response, string $op): void
    {
        if (! $response->successful()) {
            throw new \RuntimeException("OpenAI {$op} HTTP {$response->status()}: {$response->body()}");
        }
    }

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
