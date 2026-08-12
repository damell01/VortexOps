<?php

namespace App\AI\Contracts;

/**
 * A provider that can fetch models on demand (Ollama's `/api/pull`). Cloud
 * providers don't pull, so this is separate from AIProvider — callers check
 * `instanceof` before offering to download anything.
 */
interface PullsModels
{
    /**
     * Download a model, streaming progress. Returns true once the model is
     * available locally.
     *
     * @param callable(array<string,mixed>):void|null $onProgress Receives each
     *        decoded progress frame (status, and total/completed while downloading).
     */
    public function pull(string $model, ?callable $onProgress = null): bool;
}
