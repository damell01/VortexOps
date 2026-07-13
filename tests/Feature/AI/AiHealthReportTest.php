<?php

namespace Tests\Feature\AI;

use App\AI\Contracts\AIProvider;
use App\AI\Services\AiHealthReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiHealthReportTest extends TestCase
{
    use RefreshDatabase;

    /** @param string[] $available */
    private function bindProvider(bool $healthy, array $available): void
    {
        $fake = new class($healthy, $available) implements AIProvider {
            /** @param string[] $available */
            public function __construct(private bool $healthy, private array $available) {}
            public function name(): string { return 'ollama'; }
            public function chat(array $m, string $model, array $o = []): string { return ''; }
            public function stream(array $m, string $model, array $o = []): \Generator { yield ''; }
            public function vision(string $p, string $b, string $model, array $o = []): string { return ''; }
            public function embed(string $t, string $model): ?array { return null; }
            public function listModels(): array { return $this->available; }
            public function isHealthy(): bool { return $this->healthy; }
        };
        $this->app->instance(AIProvider::class, $fake);
    }

    private function report(): array
    {
        return app(AiHealthReport::class)->generate();
    }

    public function test_unreachable_backend_is_unhealthy_and_short_circuits(): void
    {
        $this->bindProvider(false, []);

        $report = $this->report();

        $this->assertSame('unhealthy', $report['status']);
        $this->assertFalse($report['reachable']);
        // Short-circuits: no model checks appended.
        $labels = array_column($report['checks'], 'label');
        $this->assertNotContains('Model · Chat', $labels);
    }

    public function test_all_models_present_is_healthy_or_degraded_not_unhealthy(): void
    {
        $this->bindProvider(true, ['llama3.2:3b', 'llama3.2:1b', 'moondream', 'nomic-embed-text:latest']);

        $report = $this->report();

        $this->assertNotSame('unhealthy', $report['status']);
        $this->assertSame([], $report['missing_models']);
    }

    public function test_missing_critical_model_is_unhealthy_and_listed(): void
    {
        $this->bindProvider(true, ['llama3.2:3b', 'llama3.2:1b', 'moondream']); // no embedding model

        $report = $this->report();

        $this->assertSame('unhealthy', $report['status']);
        $this->assertContains('nomic-embed-text', $report['missing_models']);

        // The embedding check carries the critical state + the fix hint.
        $embedding = collect($report['checks'])->firstWhere('label', 'Model · Embedding');
        $this->assertSame(AiHealthReport::CRITICAL, $embedding['state']);
        $this->assertStringContainsString('ollama pull nomic-embed-text', $embedding['detail']);
    }

    public function test_latest_tag_tolerance(): void
    {
        $this->bindProvider(true, ['nomic-embed-text:latest']);
        $svc = app(AiHealthReport::class);

        $this->assertTrue($svc->isPulled('nomic-embed-text', ['nomic-embed-text:latest']));
        $this->assertFalse($svc->isPulled('nomic-embed-text', ['moondream']));
    }
}
