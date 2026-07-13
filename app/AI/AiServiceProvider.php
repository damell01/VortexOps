<?php

namespace App\AI;

use App\AI\Contracts\AIProvider;
use App\AI\Contracts\MemoryStore;
use App\AI\Memory\CacheMemoryStore;
use App\AI\Memory\ConversationMemory;
use App\AI\Providers\OllamaProvider;
use App\AI\Providers\ProviderManager;
use App\AI\Services\AiGateway;
use App\AI\Services\IntentRouter;
use App\AI\Services\ModelRouter;
use App\AI\Services\ToolRegistry;
use App\AI\Tools\InventoryLookupTool;
use App\AI\Tools\PayoutBatchStatusTool;
use App\AI\Tools\PendingDeductionsTool;
use App\AI\Tools\ReorderListTool;
use App\AI\Tools\ShowPnlTool;
use App\AI\Tools\StreamerBalanceTool;
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

        $this->app->singleton(ToolRegistry::class);
        $this->app->singleton(IntentRouter::class);

        // Conversation memory, cache-backed by default.
        $this->app->singleton(MemoryStore::class, fn ($app) => new CacheMemoryStore($app->make('cache.store')));
        $this->app->singleton(ConversationMemory::class);
    }

    /**
     * The tools the AI can call. New tools register here — the intent router and
     * any assistant UI pick them up automatically.
     */
    public function boot(): void
    {
        // Telemetry: persist every completed AI call.
        \Illuminate\Support\Facades\Event::listen(
            \App\AI\Events\AiCallCompleted::class,
            \App\AI\Listeners\RecordAiInteraction::class,
        );

        $registry = $this->app->make(ToolRegistry::class);

        foreach ([
            InventoryLookupTool::class,
            StreamerBalanceTool::class,
            ShowPnlTool::class,
            ReorderListTool::class,
            PayoutBatchStatusTool::class,
            PendingDeductionsTool::class,
        ] as $tool) {
            $registry->register($this->app->make($tool));
        }
    }
}
