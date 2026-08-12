<?php

namespace Tests\Feature\AI;

use App\AI\Providers\OpenAiProvider;
use App\AI\Services\AiGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenAiProviderTest extends TestCase
{
    use RefreshDatabase;

    private function provider(): OpenAiProvider
    {
        return new OpenAiProvider('https://api.openai.com/v1', 'sk-test');
    }

    public function test_chat_sends_openai_shape_and_returns_content(): void
    {
        Http::fake([
            '*/chat/completions' => Http::response(['choices' => [['message' => ['content' => 'hello from gpt']]]]),
        ]);

        $content = $this->provider()->chat(
            [['role' => 'user', 'content' => 'hi']],
            'gpt-4o-mini',
            ['temperature' => 0.3, 'max_tokens' => 100],
        );

        $this->assertSame('hello from gpt', $content);
        Http::assertSent(function ($request) {
            $body = $request->data();
            return $request->url() === 'https://api.openai.com/v1/chat/completions'
                && $body['model'] === 'gpt-4o-mini'
                && $body['temperature'] === 0.3
                && $body['max_tokens'] === 100
                && $body['messages'][0] === ['role' => 'user', 'content' => 'hi']
                && $request->hasHeader('Authorization', 'Bearer sk-test');
        });
    }

    public function test_json_format_sets_response_format(): void
    {
        Http::fake(['*/chat/completions' => Http::response(['choices' => [['message' => ['content' => '{}']]]])]);

        $this->provider()->chat([['role' => 'user', 'content' => 'x']], 'gpt-4o-mini', ['format' => 'json']);

        Http::assertSent(fn ($request) => ($request->data()['response_format'] ?? null) === ['type' => 'json_object']);
    }

    public function test_vision_builds_an_image_content_part(): void
    {
        Http::fake(['*/chat/completions' => Http::response(['choices' => [['message' => ['content' => 'a slip']]]])]);

        $this->provider()->vision('read this', base64_encode('imgbytes'), 'gpt-4o');

        Http::assertSent(function ($request) {
            $content = $request->data()['messages'][0]['content'];
            return is_array($content)
                && $content[0]['type'] === 'text'
                && $content[1]['type'] === 'image_url'
                && str_starts_with($content[1]['image_url']['url'], 'data:image/jpeg;base64,');
        });
    }

    public function test_embed_returns_the_vector(): void
    {
        Http::fake(['*/embeddings' => Http::response(['data' => [['embedding' => [0.1, 0.2, 0.3]]]])]);

        $this->assertSame([0.1, 0.2, 0.3], $this->provider()->embed('some text', 'text-embedding-3-small'));
    }

    public function test_list_models_and_health(): void
    {
        Http::fake(['*/models' => Http::response(['data' => [['id' => 'gpt-4o'], ['id' => 'gpt-4o-mini']]])]);

        $this->assertSame(['gpt-4o', 'gpt-4o-mini'], $this->provider()->listModels());
        $this->assertTrue($this->provider()->isHealthy());
    }

    public function test_http_error_raises(): void
    {
        Http::fake(['*/chat/completions' => Http::response('nope', 401)]);

        $this->expectException(\RuntimeException::class);
        $this->provider()->chat([['role' => 'user', 'content' => 'x']], 'gpt-4o-mini');
    }

    public function test_streaming_yields_deltas(): void
    {
        $sse = "data: {\"choices\":[{\"delta\":{\"content\":\"Hel\"}}]}\n"
             . "data: {\"choices\":[{\"delta\":{\"content\":\"lo\"}}]}\n"
             . "data: [DONE]\n";
        Http::fake(['*/chat/completions' => Http::response($sse)]);

        $chunks = iterator_to_array($this->provider()->stream([['role' => 'user', 'content' => 'hi']], 'gpt-4o-mini'));

        $this->assertSame(['Hel', 'lo'], $chunks);
    }

    public function test_gateway_selects_openai_when_configured(): void
    {
        config()->set('ai.default_provider', 'openai');
        $this->app->forgetInstance(\App\AI\Contracts\AIProvider::class);
        $this->app->forgetInstance(AiGateway::class);

        $this->assertSame('openai', app(AiGateway::class)->provider()->name());
    }

    public function test_setting_overrides_config_for_provider_selection(): void
    {
        // Config says ollama, but the owner picked openai in the UI.
        config()->set('ai.default_provider', 'ollama');
        \App\Models\Setting::set('ai_provider', 'openai');
        $this->app->forgetInstance(\App\AI\Contracts\AIProvider::class);
        $this->app->forgetInstance(AiGateway::class);

        $this->assertSame('openai', app(AiGateway::class)->provider()->name());
    }
}
