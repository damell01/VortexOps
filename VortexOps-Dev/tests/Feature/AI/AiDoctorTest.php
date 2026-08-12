<?php

namespace Tests\Feature\AI;

use App\AI\Contracts\AIProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiDoctorTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param string[] $available
     */
    private function bindProvider(bool $healthy, array $available): void
    {
        $fake = new class($healthy, $available) implements AIProvider {
            /** @param string[] $available */
            public function __construct(private bool $healthy, private array $available) {}
            public function name(): string { return 'ollama'; }
            public function chat(array $messages, string $model, array $options = []): string { return ''; }
            public function stream(array $messages, string $model, array $options = []): \Generator { yield ''; }
            public function vision(string $prompt, string $base64Image, string $model, array $options = []): string { return ''; }
            public function embed(string $text, string $model): ?array { return null; }
            public function listModels(): array { return $this->available; }
            public function isHealthy(): bool { return $this->healthy; }
        };
        $this->app->instance(AIProvider::class, $fake);
    }

    public function test_reports_unhealthy_when_backend_unreachable(): void
    {
        $this->bindProvider(healthy: false, available: []);

        $this->artisan('vortex:ai-doctor')
            ->expectsOutputToContain('NOT reachable')
            ->assertExitCode(1);
    }

    public function test_healthy_when_all_critical_models_pulled(): void
    {
        // The config defaults: chat/reasoning/json -> llama3.2:3b, fast ->
        // llama3.2:1b, vision -> moondream, embedding -> nomic-embed-text.
        $this->bindProvider(healthy: true, available: [
            'llama3.2:3b', 'llama3.2:1b', 'moondream', 'nomic-embed-text:latest',
        ]);

        // ":latest" tolerance means nomic-embed-text still counts as pulled.
        $this->artisan('vortex:ai-doctor')->assertExitCode(0);
    }

    public function test_fails_when_a_critical_model_is_missing(): void
    {
        // Embedding model absent -> critical failure.
        $this->bindProvider(healthy: true, available: ['llama3.2:3b', 'llama3.2:1b', 'moondream']);

        $this->artisan('vortex:ai-doctor')
            ->expectsOutputToContain('ollama pull nomic-embed-text')
            ->assertExitCode(1);
    }

    public function test_pull_flag_downloads_missing_models_and_recovers(): void
    {
        // A pull-capable provider that starts without the embedding model and
        // "downloads" whatever pull() is asked for.
        $fake = new class implements \App\AI\Contracts\AIProvider, \App\AI\Contracts\PullsModels {
            public array $available = ['llama3.2:3b', 'llama3.2:1b', 'moondream'];
            public array $pulled = [];
            public function name(): string { return 'ollama'; }
            public function chat(array $m, string $model, array $o = []): string { return ''; }
            public function stream(array $m, string $model, array $o = []): \Generator { yield ''; }
            public function vision(string $p, string $b, string $model, array $o = []): string { return ''; }
            public function embed(string $t, string $model): ?array { return null; }
            public function listModels(): array { return $this->available; }
            public function isHealthy(): bool { return true; }
            public function pull(string $model, ?callable $onProgress = null): bool
            {
                $this->pulled[] = $model;
                $this->available[] = $model;
                if ($onProgress) { $onProgress(['status' => 'success']); }
                return true;
            }
        };
        $this->app->instance(\App\AI\Contracts\AIProvider::class, $fake);

        // With --pull, the missing nomic-embed-text is fetched and the run passes.
        $this->artisan('vortex:ai-doctor --pull')->assertExitCode(0);

        $this->assertContains('nomic-embed-text', $fake->pulled);
    }
}
