<?php

namespace Tests\Feature\AI;

use App\Models\Setting;
use App\Models\Show;
use App\Models\User;
use App\Services\AI\OllamaClient;
use App\Services\AI\OpsDigestService;
use App\Support\AdminModules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
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

    public function test_returns_null_when_ai_module_disabled(): void
    {
        Setting::set('enabled_admin_modules', json_encode(['streams']));
        AdminModules::flushMemo();
        $this->makeThisWeekShow();

        $ollama = Mockery::mock(OllamaClient::class);
        $ollama->shouldNotReceive('isOnline');
        $ollama->shouldNotReceive('generate');
        $this->app->instance(OllamaClient::class, $ollama);

        $this->assertNull(app(OpsDigestService::class)->generate());
    }

    public function test_returns_null_when_nothing_to_summarize(): void
    {
        $this->enableAiModule();

        $ollama = Mockery::mock(OllamaClient::class);
        $ollama->shouldNotReceive('isOnline');
        $ollama->shouldNotReceive('generate');
        $this->app->instance(OllamaClient::class, $ollama);

        $this->assertNull(app(OpsDigestService::class)->generate());
    }

    public function test_returns_null_when_ollama_offline(): void
    {
        $this->enableAiModule();
        $this->makeThisWeekShow();

        $ollama = Mockery::mock(OllamaClient::class);
        $ollama->shouldReceive('isOnline')->once()->andReturn(false);
        $ollama->shouldNotReceive('generate');
        $this->app->instance(OllamaClient::class, $ollama);

        $this->assertNull(app(OpsDigestService::class)->generate());
    }

    public function test_returns_generated_narrative_when_available(): void
    {
        $this->enableAiModule();
        $this->makeThisWeekShow();

        $ollama = Mockery::mock(OllamaClient::class);
        $ollama->shouldReceive('isOnline')->once()->andReturn(true);
        $ollama->shouldReceive('generate')->once()->andReturn("  Revenue is looking strong this week.  \n");
        $this->app->instance(OllamaClient::class, $ollama);

        $this->assertSame('Revenue is looking strong this week.', app(OpsDigestService::class)->generate());
    }

    public function test_returns_null_when_generation_throws(): void
    {
        $this->enableAiModule();
        $this->makeThisWeekShow();

        $ollama = Mockery::mock(OllamaClient::class);
        $ollama->shouldReceive('isOnline')->once()->andReturn(true);
        $ollama->shouldReceive('generate')->once()->andThrow(new \RuntimeException('timeout'));
        $this->app->instance(OllamaClient::class, $ollama);

        $this->assertNull(app(OpsDigestService::class)->generate());
    }

    public function test_returns_null_for_empty_generated_text(): void
    {
        $this->enableAiModule();
        $this->makeThisWeekShow();

        $ollama = Mockery::mock(OllamaClient::class);
        $ollama->shouldReceive('isOnline')->once()->andReturn(true);
        $ollama->shouldReceive('generate')->once()->andReturn("   \n");
        $this->app->instance(OllamaClient::class, $ollama);

        $this->assertNull(app(OpsDigestService::class)->generate());
    }

    // ── generateForRange (on-demand, arbitrary period) ────────────────────────

    public function test_range_returns_null_when_ai_module_disabled(): void
    {
        Setting::set('enabled_admin_modules', json_encode(['streams']));
        AdminModules::flushMemo();
        $this->makeThisWeekShow();

        $ollama = Mockery::mock(OllamaClient::class);
        $ollama->shouldNotReceive('isOnline');
        $ollama->shouldNotReceive('generate');
        $this->app->instance(OllamaClient::class, $ollama);

        $this->assertNull(app(OpsDigestService::class)->generateForRange(now()->subDays(30), now()));
    }

    public function test_range_returns_null_when_nothing_to_summarize(): void
    {
        $this->enableAiModule();

        $ollama = Mockery::mock(OllamaClient::class);
        $ollama->shouldNotReceive('isOnline');
        $ollama->shouldNotReceive('generate');
        $this->app->instance(OllamaClient::class, $ollama);

        $this->assertNull(app(OpsDigestService::class)->generateForRange(now()->subDays(30), now()));
    }

    public function test_range_returns_generated_narrative_when_available(): void
    {
        $this->enableAiModule();
        Show::create([
            'title' => 'Range Show', 'show_date' => now()->subDays(5)->toDateString(),
            'status' => 'reconciled', 'gross_revenue' => 500, 'created_by' => $this->creator->id,
        ]);

        $ollama = Mockery::mock(OllamaClient::class);
        $ollama->shouldReceive('isOnline')->once()->andReturn(true);
        $ollama->shouldReceive('generate')->once()->andReturn('Solid period overall.');
        $this->app->instance(OllamaClient::class, $ollama);

        $result = app(OpsDigestService::class)->generateForRange(now()->subDays(10), now());

        $this->assertSame('Solid period overall.', $result);
    }

    public function test_range_returns_null_when_ollama_offline(): void
    {
        $this->enableAiModule();
        Show::create([
            'title' => 'Range Show', 'show_date' => now()->subDays(5)->toDateString(),
            'status' => 'reconciled', 'gross_revenue' => 500, 'created_by' => $this->creator->id,
        ]);

        $ollama = Mockery::mock(OllamaClient::class);
        $ollama->shouldReceive('isOnline')->once()->andReturn(false);
        $ollama->shouldNotReceive('generate');
        $this->app->instance(OllamaClient::class, $ollama);

        $this->assertNull(app(OpsDigestService::class)->generateForRange(now()->subDays(10), now()));
    }
}
