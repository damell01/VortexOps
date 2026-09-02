<?php

namespace Tests\Feature\AI;

use App\Jobs\GenerateAiOpsInsightsJob;
use App\Models\InventoryItem;
use App\Models\User;
use App\Services\AI\OllamaClient;
use App\Services\AI\Ops\AiOpsDispatcher;
use App\Services\ProductMatchingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BackgroundOpsTest extends TestCase
{
    use RefreshDatabase;

    public function test_ops_dispatcher_only_queues_ai_work(): void
    {
        Queue::fake();

        $task = app(AiOpsDispatcher::class)->dispatch('inventory', force: true);

        $this->assertNotNull($task);
        $this->assertSame('pending', $task->status);
        $this->assertSame('ops_inventory', $task->type);
        $this->assertTrue((bool) ($task->input['background_only'] ?? false));

        Queue::assertPushedOn('ai', GenerateAiOpsInsightsJob::class);
    }

    public function test_web_request_matching_can_skip_ollama_embeddings(): void
    {
        // ProductMatchingService still uses EmbeddingService's cheap text
        // normalization during fuzzy scoring, but no model request is allowed.
        $ollama = $this->mock(OllamaClient::class);
        $ollama->shouldNotReceive('embed');
        $ollama->shouldNotReceive('generate');

        InventoryItem::create([
            'name' => '2026 Bowman Baseball Hobby Box',
            'sku' => 'BOW-26-HBY',
            'is_active' => true,
        ]);

        $result = app(ProductMatchingService::class)->match(
            '2026 Bowman Baseball Hobby',
            skipEmbedding: true,
        );

        $this->assertContains($result['stage'], ['alias', 'fuzzy', 'none']);
        $this->assertNotSame('embedding', $result['stage']);
    }
}
