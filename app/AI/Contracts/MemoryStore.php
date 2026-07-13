<?php

namespace App\AI\Contracts;

/**
 * Low-level persistence for conversation memory: an append-only list per key,
 * capped in length and expiring after a TTL. The cache-backed default keeps
 * memory fast and self-cleaning; a DB-backed store could implement this later
 * for durable history without touching anything above it.
 */
interface MemoryStore
{
    /**
     * Append an entry, trimming the list to the newest $cap entries and
     * (re)setting its expiry to $ttl seconds.
     *
     * @param array<string,mixed> $entry
     */
    public function append(string $key, array $entry, int $cap, int $ttl): void;

    /**
     * The stored entries for a key, oldest first.
     *
     * @return list<array<string,mixed>>
     */
    public function get(string $key): array;

    public function forget(string $key): void;
}
