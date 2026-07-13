<?php

namespace App\AI\Services;

use App\AI\DTOs\AiMessage;
use App\AI\Enums\AiTask;
use App\AI\Memory\ConversationMemory;
use App\AI\Prompts\PromptLibrary;
use App\Models\User;
use App\Services\AI\Chat\ChatService;

/**
 * The assistant "agent": given a user message, it decides whether a tool can
 * answer it, runs that tool against live data, and has the chat model phrase a
 * grounded reply — otherwise it falls back to ordinary chat. It remembers the
 * conversation per user (ConversationMemory), so replies build on earlier
 * turns. This is the single orchestration point the UI talks to, so no
 * routing/tool/memory logic leaks into Livewire or Filament.
 */
final class AssistantService
{
    public function __construct(
        private readonly IntentRouter       $intent,
        private readonly ToolRegistry       $tools,
        private readonly AiGateway          $gateway,
        private readonly PromptLibrary      $prompts,
        private readonly ChatService        $chat,
        private readonly ConversationMemory $memory,
    ) {}

    /**
     * Stream a reply, remembering both sides of the exchange. Prior turns are
     * recalled from memory and fed to the model; the streamed reply is recorded
     * once the stream completes.
     *
     * @return \Generator<int,string,void,void>
     */
    public function stream(string $path, string $message, ?User $user, ?string $thread = null): \Generator
    {
        $key     = $this->memory->key($user, $thread);
        $history = $this->memory->recall($key);
        $this->memory->record($key, AiMessage::user($message));

        $grounding = $this->ground($message, $user);

        $stream = $grounding === null
            ? $this->chat->stream($path, $history, $message)
            : $this->gateway->stream(AiTask::Chat, $this->groundedMessages($path, $message, $grounding, $history));

        return $this->recordingStream($key, $stream);
    }

    /**
     * Non-streaming reply with metadata (which tool ran, its structured data),
     * also remembered.
     *
     * @return array{content:string, success:bool, tool:?string, data:array, latency_ms:int}
     */
    public function answer(string $path, string $message, ?User $user, ?string $thread = null): array
    {
        $key     = $this->memory->key($user, $thread);
        $history = $this->memory->recall($key);
        $this->memory->record($key, AiMessage::user($message));

        $grounding = $this->ground($message, $user);

        if ($grounding === null) {
            $result = $this->chat->complete($path, $history, $message);

            $this->rememberReply($key, $result['content'], $result['success']);

            return [
                'content'    => $result['content'],
                'success'    => $result['success'],
                'tool'       => null,
                'data'       => [],
                'latency_ms' => $result['latency_ms'],
            ];
        }

        $response = $this->gateway->chat(AiTask::Chat, $this->groundedMessages($path, $message, $grounding, $history));

        $content = $response->success ? $response->content : "Error: {$response->error}";
        $this->rememberReply($key, $content, $response->success);

        return [
            'content'    => $content,
            'success'    => $response->success,
            'tool'       => $grounding['tool'],
            'data'       => $grounding['data'],
            'latency_ms' => $response->latencyMs,
        ];
    }

    /** Wipe a user's conversation memory (e.g. the "clear chat" button). */
    public function forget(?User $user, ?string $thread = null): void
    {
        $this->memory->forget($this->memory->key($user, $thread));
    }

    /**
     * Wrap a stream so the assembled reply is written to memory once the caller
     * finishes consuming it — keeping all memory bookkeeping inside the service.
     *
     * @param  \Generator<int,string,void,void> $stream
     * @return \Generator<int,string,void,void>
     */
    private function recordingStream(string $key, \Generator $stream): \Generator
    {
        $full = '';
        foreach ($stream as $chunk) {
            $full .= $chunk;
            yield $chunk;
        }

        if (trim($full) !== '') {
            $this->memory->record($key, AiMessage::assistant($full));
        }
    }

    private function rememberReply(string $key, string $content, bool $success): void
    {
        if ($success && trim($content) !== '') {
            $this->memory->record($key, AiMessage::assistant($content));
        }
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
     * System prompt + recalled history + the grounding turn, so a tool-answered
     * reply still has the conversation's context (e.g. a follow-up "and last
     * week?" after a P&L question).
     *
     * @param  array{tool:string, summary:string, data:array} $grounding
     * @param  list<array{role:string,content:string}> $history
     * @return list<array{role:string,content:string}>
     */
    private function groundedMessages(string $path, string $message, array $grounding, array $history = []): array
    {
        return array_merge(
            [['role' => 'system', 'content' => $this->chat->systemPrompt($path)]],
            $history,
            [['role' => 'user', 'content' => $this->prompts->answerFromToolResult($message, $grounding['tool'], $grounding['summary'])]],
        );
    }
}
