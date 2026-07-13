<?php

namespace App\Services\AI\Chat;

use App\AI\Enums\AiTask;
use App\AI\Services\AiGateway;

/**
 * Powers both AiChatPanel (streaming sidebar) and AiAssistant (full-page).
 * Combines SkillRegistry expertise instructions with ContextBuilder page data.
 *
 * All model traffic goes through AiGateway, so the chat model and generation
 * defaults come from Settings (ModelRouter) — this service owns the prompt, not
 * the model choice.
 */
class ChatService
{
    public function __construct(
        private readonly AiGateway      $gateway,
        private readonly ContextBuilder $context,
    ) {}

    /**
     * Build the full system prompt for a given page path.
     */
    public function systemPrompt(string $path): string
    {
        $skill        = SkillRegistry::detectFromPath($path);
        $instructions = SkillRegistry::instructions($skill);
        $pageContext  = $this->context->buildPageContext($path);
        $business     = $this->context->buildBusinessSummary();

        return implode("\n\n", array_filter([
            $instructions,
            $business,
            $pageContext ?: null,
        ]));
    }

    /**
     * Streaming chat — yields string content chunks.
     *
     * @param list<array{role:string,content:string}> $history  Recent conversation turns
     * @return \Generator<int,string,void,void>
     */
    public function stream(string $path, array $history, string $userMessage): \Generator
    {
        return $this->gateway->stream(
            AiTask::Chat,
            $this->buildMessages($path, $history, $userMessage),
        );
    }

    /**
     * Non-streaming completion. Returns response content + metadata.
     *
     * @param  list<array{role:string,content:string}> $history
     * @return array{content:string, latency_ms:int, success:bool, skill:string}
     */
    public function complete(string $path, array $history, string $userMessage): array
    {
        $skill = SkillRegistry::detectFromPath($path);

        $response = $this->gateway->chat(
            AiTask::Chat,
            $this->buildMessages($path, $history, $userMessage),
            ['timeout' => 240],
        );

        return [
            'content'    => $response->success ? ($response->content ?: '(empty response)') : "Error: {$response->error}",
            'latency_ms' => $response->latencyMs,
            'success'    => $response->success,
            'skill'      => $skill,
        ];
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    /** @return list<array{role:string,content:string}> */
    private function buildMessages(string $path, array $history, string $userMessage): array
    {
        return array_merge(
            [['role' => 'system', 'content' => $this->systemPrompt($path)]],
            array_slice($history, -10),
            [['role' => 'user', 'content' => $userMessage]],
        );
    }
}
