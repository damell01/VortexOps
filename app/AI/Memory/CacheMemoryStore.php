<?php

namespace App\AI\Memory;

use App\AI\Contracts\MemoryStore;
use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * Cache-backed conversation memory. Each key holds a plain array of entries;
 * appending trims to the newest $cap and refreshes the sliding TTL, so an idle
 * conversation expires on its own and an active one stays warm. No schema, no
 * cleanup job.
 */
final class CacheMemoryStore implements MemoryStore
{
    public function __construct(
        private readonly Cache $cache,
    ) {}

    public function append(string $key, array $entry, int $cap, int $ttl): void
    {
        $entries   = $this->get($key);
        $entries[] = $entry;

        if (count($entries) > $cap) {
            $entries = array_slice($entries, -$cap);
        }

        $this->cache->put($key, $entries, $ttl);
    }

    public function get(string $key): array
    {
        $entries = $this->cache->get($key, []);

        return is_array($entries) ? array_values($entries) : [];
    }

    public function forget(string $key): void
    {
        $this->cache->forget($key);
    }
}
