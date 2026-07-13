<?php

namespace App\AI\DTOs;

/**
 * The outcome of running a Tool. `summary` is model/human-readable prose the
 * assistant can weave into a reply; `data` is the structured payload a UI can
 * render directly.
 */
final class ToolResult
{
    /** @param array<string,mixed> $data */
    public function __construct(
        public readonly bool $success,
        public readonly string $summary,
        public readonly array $data = [],
    ) {}

    /** @param array<string,mixed> $data */
    public static function ok(string $summary, array $data = []): self
    {
        return new self(true, $summary, $data);
    }

    public static function error(string $summary): self
    {
        return new self(false, $summary);
    }
}
