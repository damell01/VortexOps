<?php

namespace App\AI\Memory;

use App\AI\Contracts\MemoryStore;
use App\AI\DTOs\AiMessage;
use App\Models\User;

/**
 * The assistant's short-term memory of a conversation. Keys by user (falling
 * back to session for guests) and an optional thread, so the sidebar, the
 * full-page assistant, and any future surface each keep their own running
 * history that survives across requests without a database.
 */
final class ConversationMemory
{
    public function __construct(
        private readonly MemoryStore $store,
    ) {}

    /** A stable memory key for a user + thread. */
    public function key(?User $user, ?string $thread = null): string
    {
        $who = $user?->getKey() ? "u{$user->getKey()}" : 'guest:' . session()->getId();

        return 'ai:mem:' . $who . ($thread ? ":{$thread}" : '');
    }

    /** Append a turn to memory. */
    public function record(string $key, AiMessage $message): void
    {
        $this->store->append($key, $message->toArray(), $this->turns(), $this->ttl());
    }

    /**
     * The most recent turns, oldest first, as wire arrays ready to feed a model.
     *
     * @return list<array{role:string,content:string}>
     */
    public function recall(string $key, ?int $limit = null): array
    {
        $entries = $this->store->get($key);
        $limit ??= $this->turns();

        return array_slice($entries, -$limit);
    }

    public function forget(string $key): void
    {
        $this->store->forget($key);
    }

    private function ttl(): int
    {
        return max(60, (int) config('ai.memory.ttl', 3600));
    }

    private function turns(): int
    {
        return max(2, (int) config('ai.memory.turns', 20));
    }
}
