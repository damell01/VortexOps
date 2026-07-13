<?php

namespace App\AI\Providers;

use App\AI\Contracts\AIProvider;
use App\AI\Exceptions\UnsupportedProviderException;
use Illuminate\Contracts\Container\Container;

/**
 * Resolves the active AI provider by driver name. Today only Ollama is wired;
 * new backends (OpenAI, Anthropic, Gemini, DeepSeek, Qwen) register here as a
 * new match arm — no call site changes when a provider is added.
 */
final class ProviderManager
{
    public function __construct(
        private readonly Container $app,
    ) {}

    public function driver(?string $name = null): AIProvider
    {
        $name ??= config('ai.default_provider', 'ollama');

        return match ($name) {
            'ollama' => $this->app->make(OllamaProvider::class),
            default  => throw UnsupportedProviderException::for($name),
        };
    }
}
