<?php

namespace App\AI\Services;

use App\AI\DTOs\IntentResolution;
use App\AI\Enums\AiTask;
use App\AI\Prompts\PromptLibrary;
use App\Models\User;

/**
 * Turns a free-text user message into a decision: run a tool (with arguments)
 * or fall back to plain chat. Uses the Fast task in JSON mode against the
 * user's authorized tool catalog, so classification is cheap and can never
 * select a tool the user isn't allowed to run.
 */
final class IntentRouter
{
    public function __construct(
        private readonly AiGateway     $gateway,
        private readonly ToolRegistry  $tools,
        private readonly PromptLibrary $prompts,
    ) {}

    public function route(string $message, ?User $user): IntentResolution
    {
        $catalog = $this->tools->catalogFor($user);

        // No tools available to this user → nothing to route to.
        if ($catalog === []) {
            return IntentResolution::chat();
        }

        $decision = $this->gateway->json([
            ['role' => 'user', 'content' => $this->prompts->intentClassifier($message, $catalog)],
        ], ['temperature' => 0]); // deterministic classification

        return $this->interpret($decision, $user);
    }

    /**
     * Validate the model's decision against reality: the tool must exist, be
     * authorized, and clear a confidence floor. Anything off → chat fallback.
     *
     * @param array<mixed>|null $decision
     */
    private function interpret(?array $decision, ?User $user): IntentResolution
    {
        $toolName = is_array($decision) ? ($decision['tool'] ?? null) : null;

        if (! is_string($toolName) || $toolName === '') {
            return IntentResolution::chat();
        }

        $tool = $this->tools->get($toolName);
        if (! $tool || ! $tool->authorize($user)) {
            return IntentResolution::chat();
        }

        $confidence = (float) ($decision['confidence'] ?? 0.0);
        if ($confidence < 0.5) {
            return IntentResolution::chat();
        }

        $arguments = is_array($decision['arguments'] ?? null) ? $decision['arguments'] : [];

        return IntentResolution::tool($toolName, $arguments, $confidence);
    }
}
