<?php

namespace Tests\Feature\AI;

use App\AI\Enums\AiTask;
use App\AI\Services\ModelRouter;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelRouterTest extends TestCase
{
    use RefreshDatabase;

    private function router(): ModelRouter
    {
        return app(ModelRouter::class);
    }

    public function test_falls_back_to_config_default_when_no_setting(): void
    {
        config()->set('ai.tasks.chat', ['setting' => 'ollama_chat_model', 'default' => 'default-chat']);

        $this->assertSame('default-chat', $this->router()->modelFor(AiTask::Chat));
    }

    public function test_setting_overrides_config_default(): void
    {
        config()->set('ai.tasks.chat', ['setting' => 'ollama_chat_model', 'default' => 'default-chat']);
        Setting::set('ollama_chat_model', 'admin-picked');

        $this->assertSame('admin-picked', $this->router()->modelFor(AiTask::Chat));
    }

    public function test_each_task_resolves_its_own_model(): void
    {
        Setting::set('ollama_vision_model', 'moondream');
        Setting::set('ollama_embedding_model', 'nomic-embed-text');

        $this->assertSame('moondream', $this->router()->modelFor(AiTask::Vision));
        $this->assertSame('nomic-embed-text', $this->router()->modelFor(AiTask::Embedding));
    }

    public function test_generation_options_merge_settings_then_overrides(): void
    {
        config()->set('ai.generation.temperature', ['setting' => 'ai_temperature', 'default' => 0.7]);
        config()->set('ai.generation.max_tokens', ['setting' => 'ai_max_tokens', 'default' => 1024]);
        Setting::set('ai_temperature', 0.2);

        $opts = $this->router()->generationOptions(AiTask::Chat, ['max_tokens' => 256]);

        // Setting wins over config default...
        $this->assertEquals(0.2, $opts['temperature']);
        // ...and a per-call override wins over both.
        $this->assertEquals(256, $opts['max_tokens']);
    }

    public function test_json_task_forces_json_format(): void
    {
        $opts = $this->router()->generationOptions(AiTask::Json);

        $this->assertSame('json', $opts['format']);
    }

    public function test_null_overrides_do_not_wipe_defaults(): void
    {
        config()->set('ai.generation.temperature', ['setting' => 'ai_temperature', 'default' => 0.55]);

        $opts = $this->router()->generationOptions(AiTask::Chat, ['temperature' => null]);

        $this->assertEquals(0.55, $opts['temperature']);
    }
}
