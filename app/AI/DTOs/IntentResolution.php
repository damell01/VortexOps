<?php

namespace App\AI\DTOs;

/**
 * What the IntentRouter decided a user message means: either a tool to run
 * (with arguments) or a plain-chat fallback when no tool fits.
 */
final class IntentResolution
{
    /** @param array<string,mixed> $arguments */
    private function __construct(
        public readonly string $type,      // 'tool' | 'chat'
        public readonly ?string $tool,
        public readonly array $arguments,
        public readonly float $confidence,
    ) {}

    /** @param array<string,mixed> $arguments */
    public static function tool(string $tool, array $arguments, float $confidence = 1.0): self
    {
        return new self('tool', $tool, $arguments, $confidence);
    }

    public static function chat(): self
    {
        return new self('chat', null, [], 1.0);
    }

    public function isTool(): bool
    {
        return $this->type === 'tool' && $this->tool !== null;
    }
}
