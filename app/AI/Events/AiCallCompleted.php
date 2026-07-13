<?php

namespace App\AI\Events;

use App\AI\DTOs\AiResponse;

/**
 * Fired by AiGateway after every call (success or failure). Decouples the
 * gateway from what happens next — monitoring listens and persists it, and
 * anything else (alerting, tracing) can subscribe without touching the gateway.
 */
final class AiCallCompleted
{
    public function __construct(
        public readonly AiResponse $response,
    ) {}
}
