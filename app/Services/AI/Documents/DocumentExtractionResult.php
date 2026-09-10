<?php

namespace App\Services\AI\Documents;

final class DocumentExtractionResult
{
    /**
     * @param list<string> $textRows
     * @param list<list<mixed>> $tableRows
     * @param array<string,mixed> $metadata
     */
    public function __construct(
        public readonly string $extension,
        public readonly string $kind,
        public readonly array $textRows = [],
        public readonly array $tableRows = [],
        public readonly bool $requiresVision = false,
        public readonly array $metadata = [],
    ) {}

    public function hasText(): bool
    {
        return $this->textRows !== [];
    }

    public function hasTableRows(): bool
    {
        return $this->tableRows !== [];
    }
}
