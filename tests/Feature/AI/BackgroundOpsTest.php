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

    public function test_booster_description_never_selects_jumbo_box(): void
    {
        $jumbo = InventoryItem::create([
            'name' => '2026 Pokemon Ascended Heroes Jumbo Box',
            'sku' => 'ASC-JUMBO',
            'configuration' => 'Jumbo Box',
            'is_active' => true,
        ]);

        $result = app(ProductMatchingService::class)->match(
            '2026 Pokemon Ascended Heroes Booster Box 6 count',
            skipEmbedding: true,
        );

        $this->assertNull($result['product']);
        $this->assertNotContains($jumbo->id, collect($result['candidates'])->pluck('product.id')->all());
    }

    public function test_booster_description_can_match_booster_when_jumbo_also_exists(): void
    {
        InventoryItem::create([
            'name' => '2026 Pokemon Ascended Heroes Jumbo Box',
            'sku' => 'ASC-JUMBO',
            'configuration' => 'Jumbo Box',
            'is_active' => true,
        ]);

        $booster = InventoryItem::create([
            'name' => '2026 Pokemon Ascended Heroes Booster Box',
            'sku' => 'ASC-BOOSTER',
            'configuration' => 'Booster Box',
            'is_active' => true,
        ]);

        $result = app(ProductMatchingService::class)->match(
            '2026 Pokemon Ascended Heroes Booster Box 6 count',
            skipEmbedding: true,
        );

        $this->assertNotNull($result['product']);
        $this->assertSame($booster->id, $result['product']->id);
    }
}
