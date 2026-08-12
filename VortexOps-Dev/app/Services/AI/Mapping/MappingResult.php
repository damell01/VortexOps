<?php

namespace App\Services\AI\Mapping;

use App\Models\Product;
use App\Models\ProductIdentity;

/**
 * Immutable value object returned by MappingEngine::match().
 */
final class MappingResult
{
    public function __construct(
        public readonly ?Product         $product,
        public readonly float            $confidence,
        public readonly string           $stage,    // alias|fuzzy|embedding|llm|none
        public readonly array            $reasons,
        public readonly array            $candidates,
        public readonly ?ProductIdentity $identity = null,
    ) {}

    public function matched(): bool
    {
        return $this->product !== null;
    }

    public function autoAccept(): bool
    {
        return $this->confidence >= 0.95;
    }

    public function needsReview(): bool
    {
        return $this->matched() && ! $this->autoAccept();
    }

    public function confidenceLabel(): string
    {
        return match (true) {
            $this->confidence >= 1.0  => 'high',
            $this->confidence >= 0.80 => 'medium',
            default                   => 'low',
        };
    }

    public function toArray(): array
    {
        return [
            'product'    => $this->product,
            'confidence' => $this->confidence,
            'stage'      => $this->stage,
            'reasons'    => $this->reasons,
            'candidates' => $this->candidates,
            'identity'   => $this->identity,
        ];
    }
}
