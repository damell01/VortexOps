<?php

namespace App\AI;

use App\AI\Contracts\AIProvider;
use App\AI\Providers\OllamaProvider;
use App\AI\Providers\ProviderManager;
use App\AI\Services\AiGateway;
use App\AI\Services\ModelRouter;
use App\Services\AI\OllamaClient;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the AI platform. Everything is a singleton — the gateway, router, and
 * provider are stateless and reused for the life of the request. The provider is
 * resolved through ProviderManager so swapping backends is a config change.
 */
class AiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ModelRouter::class);
        $this->app->singleton(ProviderManager::class);

        $this->app->singleton(OllamaProvider::class, fn ($app) => new OllamaProvider(
            $app->make(OllamaClient::class),
        ));

        $this->app->singleton(\App\AI\Providers\OpenAiProvider::class, fn () => new \App\AI\Providers\OpenAiProvider(
            rtrim((string) config('ai.providers.openai.base_url', 'https://api.openai.com/v1'), '/'),
            config('ai.providers.openai.api_key'),
        ));

        // The active provider, resolved from config('ai.default_provider').
        $this->app->singleton(AIProvider::class, fn ($app) => $app->make(ProviderManager::class)->driver());

        $this->app->singleton(AiGateway::class, fn ($app) => new AiGateway(
            $app->make(AIProvider::class),
            $app->make(ModelRouter::class),
        ));
    }

    public function boot(): void
    {
        // Telemetry: persist every completed AI call.
        \Illuminate\Support\Facades\Event::listen(
            \App\AI\Events\AiCallCompleted::class,
            \App\AI\Listeners\RecordAiInteraction::class,
        );
    }
}
