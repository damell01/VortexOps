<?php

namespace App\AI\DTOs;

use App\AI\Enums\AiTask;

/**
 * The outcome of a single AI call, carrying enough metadata for monitoring and
 * learning to observe latency, the model used, and whether it succeeded — so
 * those concerns never have to reach into the provider layer.
 */
final class AiResponse
{
    public function __construct(
        public readonly string $content,
        public readonly string $model,
        public readonly AiTask $task,
        public readonly string $provider,
        public readonly int $latencyMs,
        public readonly bool $success = true,
        public readonly ?string $error = null,
    ) {}

    public static function failed(AiTask $task, string $model, string $provider, string $error, int $latencyMs): self
    {
        return new self('', $model, $task, $provider, $latencyMs, false, $error);
    }

    /**
     * Decode the content as JSON. Returns null when the model didn't return
     * parseable JSON (defensive — even json-mode models occasionally wrap output).
     *
     * @return array<mixed>|null
     */
    public function json(): ?array
    {
        $decoded = json_decode($this->content, true);

        return is_array($decoded) ? $decoded : null;
    }
}
