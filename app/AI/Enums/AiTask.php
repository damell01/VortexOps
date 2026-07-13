<?php

namespace App\AI\Enums;

/**
 * The purpose an AI call serves. Every request declares its task, and the
 * ModelRouter resolves the concrete model + generation defaults from that task
 * (Settings first, config fallback). No model name is ever hardcoded at a call
 * site — callers ask for a *task*, not a model.
 */
enum AiTask: string
{
    /** General conversational assistance (sidebar / full-page chat). */
    case Chat = 'chat';

    /** Latency-sensitive, low-stakes calls — quick classification, short rewrites. */
    case Fast = 'fast';

    /** Multi-step reasoning where quality matters more than speed. */
    case Reasoning = 'reasoning';

    /** Image understanding — slip parsing, screenshot reading. */
    case Vision = 'vision';

    /** Text embeddings for semantic matching. */
    case Embedding = 'embedding';

    /** Structured output — the model must return valid JSON. */
    case Json = 'json';

    public function label(): string
    {
        return match ($this) {
            self::Chat      => 'Chat',
            self::Fast      => 'Fast',
            self::Reasoning => 'Reasoning',
            self::Vision    => 'Vision',
            self::Embedding => 'Embedding',
            self::Json      => 'Structured (JSON)',
        };
    }
}
