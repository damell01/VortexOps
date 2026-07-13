<?php

namespace App\AI\Services;

use App\AI\Enums\AiTask;
use App\AI\Prompts\PromptLibrary;
use App\Models\User;
use App\Services\AI\Chat\ChatService;

/**
 * The assistant "agent": given a user message, it decides whether a tool can
 * answer it, runs that tool against live data, and has the chat model phrase a
 * grounded reply — otherwise it falls back to ordinary chat. This is the single
 * orchestration point the UI talks to, so no routing/tool logic leaks into
 * Livewire or Filament.
 */
final class AssistantService
{
    public function __construct(
        private readonly IntentRouter  $intent,
        private readonly ToolRegistry  $tools,
        private readonly AiGateway     $gateway,
        private readonly PromptLibrary $prompts,
        private readonly ChatService   $chat,
    ) {}

    /**
     * Stream a reply. When a tool grounds the answer, the model is fed the tool
     * result and told to answer only from it; otherwise it's a normal chat turn.
     *
     * @param  list<array{role:string,content:string}> $history
     * @return \Generator<int,string,void,void>
     */
    public function stream(string $path, array $history, string $message, ?User $user): \Generator
    {
        $grounding = $this->ground($message, $user);

        if ($grounding === null) {
            return $this->chat->stream($path, $history, $message);
        }

        return $this->gateway->stream(AiTask::Chat, $this->groundedMessages($path, $message, $grounding));
    }

    /**
     * Non-streaming reply with metadata (which tool ran, its structured data).
     *
     * @return array{content:string, success:bool, tool:?string, data:array, latency_ms:int}
     */
    public function answer(string $path, string $message, ?User $user): array
    {
        $grounding = $this->ground($message, $user);

        if ($grounding === null) {
            $result = $this->chat->complete($path, [], $message);

            return [
                'content'    => $result['content'],
                'success'    => $result['success'],
                'tool'       => null,
                'data'       => [],
                'latency_ms' => $result['latency_ms'],
            ];
        }

        $response = $this->gateway->chat(AiTask::Chat, $this->groundedMessages($path, $message, $grounding));

        return [
            'content'    => $response->success ? $response->content : "Error: {$response->error}",
            'success'    => $response->success,
            'tool'       => $grounding['tool'],
            'data'       => $grounding['data'],
            'latency_ms' => $response->latencyMs,
        ];
    }

    /**
     * Route the message and, if a tool is chosen, run it. Returns the grounding
     * (tool name, prose summary, structured data) or null for plain chat.
     *
     * @return array{tool:string, summary:string, data:array}|null
     */
    private function ground(string $message, ?User $user): ?array
    {
        $resolution = $this->intent->route($message, $user);

        if (! $resolution->isTool()) {
            return null;
        }

        // Re-check authorization at execution time — the catalog was filtered,
        // but never trust a resolved name without confirming the tool + access.
        $tool = $this->tools->get($resolution->tool);
        if (! $tool || ! $tool->authorize($user)) {
            return null;
        }

        $result = $tool->run($resolution->arguments);

        return [
            'tool'    => $tool->name(),
            'summary' => $result->summary,
            'data'    => $result->data,
        ];
    }

    /**
     * @param array{tool:string, summary:string, data:array} $grounding
     * @return list<array{role:string,content:string}>
     */
    private function groundedMessages(string $path, string $message, array $grounding): array
    {
        return [
            ['role' => 'system', 'content' => $this->chat->systemPrompt($path)],
            ['role' => 'user',   'content' => $this->prompts->answerFromToolResult($message, $grounding['tool'], $grounding['summary'])],
        ];
    }
}
