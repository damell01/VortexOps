<?php

namespace Tests\Feature\AI;

use App\AI\Contracts\AIProvider;
use App\Models\Setting;
use App\Models\Show;
use App\Models\User;
use App\Services\AI\OpsDigestService;
use App\Support\AdminModules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class OpsDigestServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $creator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->creator = User::factory()->create();
        $this->actingAs($this->creator);
    }

    /**
     * Setting::set('enabled_admin_modules', ...) forgets the cache key at
     * write time, but RefreshDatabase only rolls back the DB — the cache
     * store isn't request/test-scoped, so a narrowed module list written
     * here would otherwise leak into whatever test runs next in the same
     * PHPUnit process and 403 it. Forget the key again post-test so the
     * next reader re-queries the (rolled-back) DB instead of a stale cache.
     */
    protected function tearDown(): void
    {
        Cache::forget('setting:enabled_admin_modules');
        AdminModules::flushMemo();
        parent::tearDown();
    }

    private function enableAiModule(): void
    {
        Setting::set('enabled_admin_modules', json_encode(['streams', 'ai']));
        AdminModules::flushMemo();
    }

    private function makeThisWeekShow(float $revenue = 500): void
    {
        Show::create([
            'title' => 'Test Show', 'show_date' => now()->startOfWeek()->toDateString(),
            'status' => 'reconciled', 'gross_revenue' => $revenue, 'created_by' => $this->creator->id,
        ]);
    }

    /**
     * Bind a fake provider through the real AiGateway/ModelRouter, so these
     * tests also verify the digest actually routes through the Reasoning
     * task rather than hardcoding a model — same pattern PalletSlipParserTest
     * uses for the vision task.
     */
    private function fakeProvider(?string $chatReturn, bool $healthy = true, ?\Throwable $throws = null): object
    {
        $fake = new class($chatReturn, $healthy, $throws) implements AIProvider {
            public array $lastChat = [];
            public bool $chatCalled = false;
            public bool $isHealthyCalled = false;

            public function __construct(
                private ?string $chatReturn,
                private bool $healthy,
                private ?\Throwable $throws,
            ) {}

            public function name(): string { return 'fake'; }

            public function chat(array $messages, string $model, array $options = []): string
            {
                $this->chatCalled = true;
                $this->lastChat = compact('messages', 'model', 'options');

                if ($this->throws) {
                    throw $this->throws;
                }

                return $this->chatReturn ?? '';
            }

            public function stream(array $m, string $model, array $o = []): \Generator { yield ''; }
            public function vision(string $p, string $i, string $model, array $o = []): string { return ''; }
            public function embed(string $t, string $model): ?array { return null; }
            public function listModels(): array { return []; }

            public function isHealthy(): bool
            {
                $this->isHealthyCalled = true;

                return $this->healthy;
            }
        };
        $this->app->instance(AIProvider::class, $fake);

        return $fake;
    }

    // ── generate() (weekly digest) ────────────────────────────────────────────

    public function test_returns_null_when_ai_module_disabled(): void
    {
        Setting::set('enabled_admin_modules', json_encode(['streams']));
        AdminModules::flushMemo();
        $this->makeThisWeekShow();

        $fake = $this->fakeProvider('Revenue is looking strong this week.');

        $this->assertNull(app(OpsDigestService::class)->generate());
        $this->assertFalse($fake->isHealthyCalled);
        $this->assertFalse($fake->chatCalled);
    }

    public function test_returns_null_when_nothing_to_summarize(): void
    {
        $this->enableAiModule();

        $fake = $this->fakeProvider('Revenue is looking strong this week.');

        $this->assertNull(app(OpsDigestService::class)->generate());
        $this->assertFalse($fake->chatCalled);
    }

    public function test_returns_null_when_provider_offline(): void
    {
        $this->enableAiModule();
        $this->makeThisWeekShow();

        $fake = $this->fakeProvider('Revenue is looking strong this week.', healthy: false);

        $this->assertNull(app(OpsDigestService::class)->generate());
        $this->assertFalse($fake->chatCalled);
    }

    public function test_returns_generated_narrative_when_available(): void
    {
        $this->enableAiModule();
        $this->makeThisWeekShow();

        $fake = $this->fakeProvider("  Revenue is looking strong this week.  \n");

        $this->assertSame('Revenue is looking strong this week.', app(OpsDigestService::class)->generate());
    }

    public function test_routes_through_the_reasoning_task(): void
    {
        $this->enableAiModule();
        $this->makeThisWeekShow();
        Setting::set('ollama_reasoning_model', 'llama3.1:8b');

        $fake = $this->fakeProvider('Solid week.');

        app(OpsDigestService::class)->generate();

        $this->assertTrue($fake->chatCalled);
        $this->assertSame('llama3.1:8b', $fake->lastChat['model']);
        $this->assertSame(90, $fake->lastChat['options']['timeout']);
    }

    public function test_returns_null_when_generation_throws(): void
    {
        $this->enableAiModule();
        $this->makeThisWeekShow();

        $this->fakeProvider(null, throws: new \RuntimeException('timeout'));

        $this->assertNull(app(OpsDigestService::class)->generate());
    }

    public function test_returns_null_for_empty_generated_text(): void
    {
        $this->enableAiModule();
        $this->makeThisWeekShow();

        $this->fakeProvider("   \n");

        $this->assertNull(app(OpsDigestService::class)->generate());
    }

    // ── generateForRange (on-demand, arbitrary period) ────────────────────────

    public function test_range_returns_null_when_ai_module_disabled(): void
    {
        Setting::set('enabled_admin_modules', json_encode(['streams']));
        AdminModules::flushMemo();
        $this->makeThisWeekShow();

        $fake = $this->fakeProvider('Solid period overall.');

        $this->assertNull(app(OpsDigestService::class)->generateForRange(now()->subDays(30), now()));
        $this->assertFalse($fake->chatCalled);
    }

    public function test_range_returns_null_when_nothing_to_summarize(): void
    {
        $this->enableAiModule();

        $fake = $this->fakeProvider('Solid period overall.');

        $this->assertNull(app(OpsDigestService::class)->generateForRange(now()->subDays(30), now()));
        $this->assertFalse($fake->chatCalled);
    }

    public function test_range_returns_generated_narrative_when_available(): void
    {
        $this->enableAiModule();
        Show::create([
            'title' => 'Range Show', 'show_date' => now()->subDays(5)->toDateString(),
            'status' => 'reconciled', 'gross_revenue' => 500, 'created_by' => $this->creator->id,
        ]);

        $this->fakeProvider('Solid period overall.');

        $result = app(OpsDigestService::class)->generateForRange(now()->subDays(10), now());

        $this->assertSame('Solid period overall.', $result);
    }

    public function test_range_returns_null_when_provider_offline(): void
    {
        $this->enableAiModule();
        Show::create([
            'title' => 'Range Show', 'show_date' => now()->subDays(5)->toDateString(),
            'status' => 'reconciled', 'gross_revenue' => 500, 'created_by' => $this->creator->id,
        ]);

        $fake = $this->fakeProvider('Solid period overall.', healthy: false);

        $this->assertNull(app(OpsDigestService::class)->generateForRange(now()->subDays(10), now()));
        $this->assertFalse($fake->chatCalled);
    }
}
