<?php

namespace App\Services\AI\Chat;

use App\Services\AI\OllamaClient;
use Illuminate\Support\Facades\Log;

/**
 * Powers both AiChatPanel (streaming sidebar) and AiAssistant (full-page).
 * Combines SkillRegistry expertise instructions with ContextBuilder page data.
 */
class ChatService
{
    public function __construct(
        private readonly OllamaClient  $client,
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
        $messages = $this->buildMessages($path, $history, $userMessage);
        return $this->client->chatStream($messages, [
            'ollama_options' => ['num_ctx' => 4096],
        ]);
    }

    /**
     * Non-streaming completion. Returns response content + metadata.
     *
     * @param  list<array{role:string,content:string}> $history
     * @return array{content:string, latency_ms:int, success:bool, skill:string}
     */
    public function complete(string $path, array $history, string $userMessage): array
    {
        $start = microtime(true);
        $skill = SkillRegistry::detectFromPath($path);

        try {
            $messages = $this->buildMessages($path, $history, $userMessage);
            $content  = $this->client->chat($messages, [
                'timeout'        => 240,
                'ollama_options' => ['num_predict' => 1024],
            ]);

            return [
                'content'    => $content ?: '(empty response)',
                'latency_ms' => (int) ((microtime(true) - $start) * 1000),
                'success'    => true,
                'skill'      => $skill,
            ];
        } catch (\Throwable $e) {
            Log::error('ChatService::complete failed', ['error' => $e->getMessage(), 'path' => $path]);

            return [
                'content'    => "Error: {$e->getMessage()}",
                'latency_ms' => (int) ((microtime(true) - $start) * 1000),
                'success'    => false,
                'skill'      => $skill,
            ];
        }
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
