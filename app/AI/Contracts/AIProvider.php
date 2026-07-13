<?php

namespace App\AI\Contracts;

/**
 * A pluggable AI backend. Ollama ships today; OpenAI / Anthropic / Gemini /
 * DeepSeek / Qwen can be added later by implementing this same surface — nothing
 * above the provider layer knows or cares which one is active.
 *
 * Options are normalised (see ModelRouter::generationOptions): temperature,
 * top_p, max_tokens, context_length, timeout, format. Each provider translates
 * them to its own wire format.
 */
interface AIProvider
{
    /** Stable identifier, e.g. "ollama". */
    public function name(): string;

    /**
     * Non-streaming chat completion. Returns the assistant's text.
     *
     * @param  list<array{role:string,content:string,images?:string[]}> $messages
     * @param  array<string,mixed> $options
     */
    public function chat(array $messages, string $model, array $options = []): string;

    /**
     * Streaming chat — yields content chunks as they arrive.
     *
     * @param  list<array{role:string,content:string,images?:string[]}> $messages
     * @param  array<string,mixed> $options
     * @return \Generator<int,string,void,void>
     */
    public function stream(array $messages, string $model, array $options = []): \Generator;

    /**
     * Vision completion — a prompt plus one base64 image.
     *
     * @param array<string,mixed> $options
     */
    public function vision(string $prompt, string $base64Image, string $model, array $options = []): string;

    /**
     * Embed text into a vector. Null when the backend is unavailable.
     *
     * @return float[]|null
     */
    public function embed(string $text, string $model): ?array;

    /** Model names the backend has available. */
    public function listModels(): array;

    /** Whether the backend is reachable right now. */
    public function isHealthy(): bool;
}
