<?php
namespace Tests\Feature\Reliability;

use App\Jobs\GenerateAiOpsInsightsJob;
use App\Services\AI\OllamaClient;
use App\Services\AI\Ops\AiOpsDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AiOpsFallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_unavailable_model_completes_and_cools_down_optional_summaries(): void
    {
        Queue::fake();
        Cache::forget('ai:ops:narrative-cooldown');
        config(['ai.ops.use_llm' => true]);
        $client = $this->mock(OllamaClient::class);
        $client->shouldReceive('isOnline')->once()->andReturn(false);
        $client->shouldNotReceive('chat');
        foreach (range(1, 2) as $attempt) {
            $task = app(AiOpsDispatcher::class)->dispatch('inventory', force: true);
            app()->call([new GenerateAiOpsInsightsJob($task->id, 'inventory'), 'handle']);
            $this->assertSame('completed', $task->fresh()->status);
        }
        $this->assertTrue(Cache::has('ai:ops:narrative-cooldown'));
    }

    public function test_malformed_narrative_fields_do_not_fail_the_job(): void
    {
        Queue::fake();
        Cache::forget('ai:ops:narrative-cooldown');
        config(['ai.ops.use_llm' => true]);
        $client = $this->mock(OllamaClient::class);
        $client->shouldReceive('isOnline')->once()->andReturn(true);
        $client->shouldReceive('chat')->once()->withArgs(fn ($messages, $options) => $options['timeout'] === 45)
            ->andReturn('{"summary":["bad"],"insights":[{"title":["bad"],"summary":"test"}]}');
        $task = app(AiOpsDispatcher::class)->dispatch('inventory', force: true);
        app()->call([new GenerateAiOpsInsightsJob($task->id, 'inventory'), 'handle']);
        $this->assertSame('completed', $task->fresh()->status);
    }
}
