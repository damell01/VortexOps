<?php

namespace Tests\Feature\AI;

use App\AI\Contracts\AIProvider;
use App\Models\Setting;
use App\Services\AI\Chat\ChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatServiceTest extends TestCase
{
    use RefreshDatabase;

    private function fakeProvider(): object
    {
        $fake = new class implements AIProvider {
            public array $lastCall = [];

            public function name(): string { return 'fake'; }

            public function chat(array $messages, string $model, array $options = []): string
            {
                $this->lastCall = compact('messages', 'model', 'options');
                return 'Here is your briefing.';
            }

            public function stream(array $messages, string $model, array $options = []): \Generator
            {
                $this->lastCall = compact('messages', 'model', 'options');
                yield 'Hello ';
                yield 'world';
            }

            public function vision(string $prompt, string $base64Image, string $model, array $options = []): string { return ''; }
            public function embed(string $text, string $model): ?array { return null; }
            public function listModels(): array { return []; }
            public function isHealthy(): bool { return true; }
        };

        $this->app->instance(AIProvider::class, $fake);

        return $fake;
    }

    public function test_complete_returns_the_expected_shape_via_the_gateway(): void
    {
        $fake = $this->fakeProvider();
        Setting::set('ollama_chat_model', 'briefing-model');

        $result = app(ChatService::class)->complete('/admin', [], 'How are we doing?');

        $this->assertTrue($result['success']);
        $this->assertSame('Here is your briefing.', $result['content']);
        $this->assertArrayHasKey('latency_ms', $result);
        $this->assertArrayHasKey('skill', $result);
        // Routed the configured chat model.
        $this->assertSame('briefing-model', $fake->lastCall['model']);
        // System prompt is always the first message.
        $this->assertSame('system', $fake->lastCall['messages'][0]['role']);
    }

    public function test_stream_yields_chunks_through_the_gateway(): void
    {
        $this->fakeProvider();

        $chunks = iterator_to_array(app(ChatService::class)->stream('/admin', [], 'hi'));

        $this->assertSame(['Hello ', 'world'], $chunks);
    }

    public function test_provider_failure_surfaces_as_a_graceful_error(): void
    {
        $throwing = new class implements AIProvider {
            public function name(): string { return 'boom'; }
            public function chat(array $messages, string $model, array $options = []): string
            {
                throw new \RuntimeException('model timed out');
            }
            public function stream(array $messages, string $model, array $options = []): \Generator { yield ''; }
            public function vision(string $prompt, string $base64Image, string $model, array $options = []): string { return ''; }
            public function embed(string $text, string $model): ?array { return null; }
            public function listModels(): array { return []; }
            public function isHealthy(): bool { return false; }
        };
        $this->app->instance(AIProvider::class, $throwing);

        $result = app(ChatService::class)->complete('/admin', [], 'hi');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('model timed out', $result['content']);
    }
}
