<?php

namespace Tests\Feature\AI;

use App\AI\Enums\AiTask;
use App\AI\Services\ModelRouter;
use App\Filament\Pages\AppSettings;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AiSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->create(['email' => config('app.owner_email', 'dbellcreations@gmail.com')]);
    }

    public function test_saving_ai_models_persists_and_routes(): void
    {
        $this->actingAs($this->owner());

        Livewire::test(AppSettings::class)
            ->set('ollama_chat_model', 'qwen3:4b')
            ->set('ollama_fast_model', 'llama3.2:1b')
            ->set('ollama_reasoning_model', 'qwen3:14b')
            ->set('ollama_json_model', 'qwen3:4b')
            ->set('ai_temperature', '0.3')
            ->set('ai_max_tokens', '2048')
            ->call('saveSettings')
            ->assertHasNoErrors();

        // Persisted...
        $this->assertSame('qwen3:4b', Setting::get('ollama_chat_model'));
        $this->assertSame('llama3.2:1b', Setting::get('ollama_fast_model'));

        // ...and the router now resolves the saved models per task.
        $router = app(ModelRouter::class);
        $this->assertSame('qwen3:14b', $router->modelFor(AiTask::Reasoning));
        $this->assertSame('llama3.2:1b', $router->modelFor(AiTask::Fast));
        $this->assertEquals(0.3, $router->generationOptions(AiTask::Chat)['temperature']);
        $this->assertEquals(2048, $router->generationOptions(AiTask::Chat)['max_tokens']);
    }

    public function test_blank_task_model_falls_back_to_chat_model(): void
    {
        $this->actingAs($this->owner());

        Livewire::test(AppSettings::class)
            ->set('ollama_chat_model', 'my-chat')
            ->set('ollama_fast_model', '')
            ->set('ollama_reasoning_model', '')
            ->set('ollama_json_model', '')
            ->call('saveSettings')
            ->assertHasNoErrors();

        $this->assertSame('my-chat', Setting::get('ollama_fast_model'));
        $this->assertSame('my-chat', Setting::get('ollama_json_model'));
    }

    public function test_temperature_out_of_range_is_rejected(): void
    {
        $this->actingAs($this->owner());

        Livewire::test(AppSettings::class)
            ->set('ai_temperature', '9')
            ->call('saveSettings')
            ->assertHasErrors('ai_temperature');
    }

    public function test_embedding_coverage_uses_the_relation_not_a_dropped_column(): void
    {
        $this->actingAs($this->owner());

        // A product with an embedding and one without.
        $a = \App\Models\Product::create(['name' => 'Has Vec', 'sku' => 'HV-1', 'is_active' => true]);
        \App\Models\ProductEmbedding::create(['product_id' => $a->id, 'vector' => [0.1, 0.2]]);
        \App\Models\Product::create(['name' => 'No Vec', 'sku' => 'NV-1', 'is_active' => true]);

        $coverage = app(AppSettings::class)->getEmbeddingCoverageProperty();

        $this->assertSame(1, $coverage['embedded']);
        $this->assertSame(2, $coverage['total']);
    }
}
