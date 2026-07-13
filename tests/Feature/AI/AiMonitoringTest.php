<?php

namespace Tests\Feature\AI;

use App\AI\Contracts\AIProvider;
use App\AI\DTOs\AiMessage;
use App\AI\Enums\AiTask;
use App\AI\Services\AiGateway;
use App\AI\Services\AiUsageReport;
use App\AI\Services\LearningService;
use App\Filament\Pages\AiMonitoring;
use App\Models\AiInteraction;
use App\Models\Product;
use App\Models\ProductIdentity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AiMonitoringTest extends TestCase
{
    use RefreshDatabase;

    private function fakeProvider(bool $throws = false): void
    {
        $fake = new class($throws) implements AIProvider {
            public function __construct(private bool $throws) {}
            public function name(): string { return 'fake'; }
            public function chat(array $m, string $model, array $o = []): string
            {
                if ($this->throws) { throw new \RuntimeException('kaboom'); }
                return 'ok';
            }
            public function stream(array $m, string $model, array $o = []): \Generator { yield 'ok'; }
            public function vision(string $p, string $b, string $model, array $o = []): string { return 'ok'; }
            public function embed(string $t, string $model): ?array { return null; }
            public function listModels(): array { return []; }
            public function isHealthy(): bool { return true; }
        };
        $this->app->instance(AIProvider::class, $fake);
    }

    public function test_gateway_call_is_logged(): void
    {
        $this->fakeProvider();

        app(AiGateway::class)->chat(AiTask::Chat, [AiMessage::user('hi')]);

        $this->assertSame(1, AiInteraction::count());
        $row = AiInteraction::first();
        $this->assertSame('chat', $row->task);
        $this->assertSame('fake', $row->provider);
        $this->assertTrue($row->success);
    }

    public function test_failed_call_is_logged_with_error(): void
    {
        $this->fakeProvider(throws: true);

        app(AiGateway::class)->chat(AiTask::Chat, [AiMessage::user('hi')]);

        $row = AiInteraction::first();
        $this->assertFalse($row->success);
        $this->assertStringContainsString('kaboom', $row->error);
    }

    public function test_monitoring_can_be_disabled(): void
    {
        config()->set('ai.monitoring.enabled', false);
        $this->fakeProvider();

        app(AiGateway::class)->chat(AiTask::Chat, [AiMessage::user('hi')]);

        $this->assertSame(0, AiInteraction::count());
    }

    public function test_usage_report_aggregates(): void
    {
        AiInteraction::insert([
            ['provider' => 'ollama', 'task' => 'chat', 'model' => 'llama3.2:3b', 'latency_ms' => 100, 'success' => true,  'error' => null,    'created_at' => now()],
            ['provider' => 'ollama', 'task' => 'chat', 'model' => 'llama3.2:3b', 'latency_ms' => 300, 'success' => true,  'error' => null,    'created_at' => now()],
            ['provider' => 'ollama', 'task' => 'json', 'model' => 'qwen2.5:3b',  'latency_ms' => 200, 'success' => false, 'error' => 'oops', 'created_at' => now()],
        ]);

        $report  = app(AiUsageReport::class);
        $summary = $report->summary(7);

        $this->assertSame(3, $summary['calls']);
        $this->assertSame(1, $summary['failed']);
        $this->assertEqualsWithDelta(66.7, $summary['success_rate'], 0.1);
        $this->assertSame(200, $summary['avg_latency']);

        $byTask = collect($report->byTask(7))->keyBy('task');
        $this->assertSame(2, $byTask['chat']['calls']);
        $this->assertSame(200, $byTask['chat']['avg_latency']);

        $failures = $report->recentFailures(5);
        $this->assertCount(1, $failures);
        $this->assertSame('oops', $failures[0]['error']);
    }

    public function test_learning_stats_count_aliases_and_confirmations(): void
    {
        $product = Product::create(['name' => 'Prizm Bball', 'sku' => 'P-1', 'is_active' => true]);
        ProductIdentity::create(['product_id' => $product->id, 'type' => ProductIdentity::TYPE_ALIAS, 'value' => 'panini prizm bball', 'times_confirmed' => 4]);
        ProductIdentity::create(['product_id' => $product->id, 'type' => ProductIdentity::TYPE_ALIAS, 'value' => 'prizm basketball wax', 'times_confirmed' => 1]);

        $stats = app(LearningService::class)->stats();

        $this->assertSame(2, $stats['aliases']);
        $this->assertSame(5, $stats['confirmations']);
        $this->assertSame('panini prizm bball', $stats['top_aliases'][0]['value']);
    }

    public function test_page_renders(): void
    {
        \App\Models\Setting::set('enabled_admin_modules', json_encode(['ai']));
        \App\Support\AdminModules::flushMemo();

        $owner = User::factory()->create(['email' => config('app.owner_email', 'dbellcreations@gmail.com')]);
        $this->actingAs($owner);

        Livewire::test(AiMonitoring::class)
            ->assertOk()
            ->assertSee('Success rate')
            ->assertSee('By Task')
            ->call('setDays', 30)
            ->assertSet('days', 30);
    }
}
