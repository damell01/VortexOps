<?php

namespace Tests\Feature\AI;

use App\AI\Contracts\AIProvider;
use App\AI\DTOs\AiMessage;
use App\AI\Enums\AiTask;
use App\AI\Services\AiGateway;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiGatewayTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Bind a fake provider that records the model/options it was called with so
     * we can assert the gateway routed the task correctly.
     */
    private function fakeProvider(): object
    {
        $fake = new class implements AIProvider {
            public array $lastCall = [];
            public string $nextContent = 'hello from the model';

            public function name(): string { return 'fake'; }

            public function chat(array $messages, string $model, array $options = []): string
            {
                $this->lastCall = compact('messages', 'model', 'options');
                return $this->nextContent;
            }

            public function stream(array $messages, string $model, array $options = []): \Generator
            {
                yield 'chunk-1';
                yield 'chunk-2';
            }

            public function vision(string $prompt, string $base64Image, string $model, array $options = []): string
            {
                $this->lastCall = compact('prompt', 'model', 'options');
                return 'a receiving slip';
            }

            public function embed(string $text, string $model): ?array { return [0.1, 0.2]; }
            public function listModels(): array { return ['fake-model']; }
            public function isHealthy(): bool { return true; }
        };

        $this->app->instance(AIProvider::class, $fake);

        return $fake;
    }

    public function test_chat_returns_a_populated_response(): void
    {
        $this->fakeProvider();
        Setting::set('ollama_chat_model', 'chat-model-x');

        $response = app(AiGateway::class)->chat(AiTask::Chat, [
            AiMessage::system('You are helpful.'),
            AiMessage::user('Hi'),
        ]);

        $this->assertTrue($response->success);
        $this->assertSame('hello from the model', $response->content);
        $this->assertSame('chat-model-x', $response->model);
        $this->assertSame('fake', $response->provider);
        $this->assertSame(AiTask::Chat, $response->task);
    }

    public function test_chat_routes_the_task_specific_model_to_the_provider(): void
    {
        $fake = $this->fakeProvider();
        Setting::set('ollama_reasoning_model', 'deep-thinker');

        app(AiGateway::class)->chat(AiTask::Reasoning, [AiMessage::user('why?')]);

        $this->assertSame('deep-thinker', $fake->lastCall['model']);
    }

    public function test_messages_are_normalised_to_wire_arrays(): void
    {
        $fake = $this->fakeProvider();

        app(AiGateway::class)->chat(AiTask::Chat, [AiMessage::user('Hi there')]);

        $this->assertSame([['role' => 'user', 'content' => 'Hi there']], $fake->lastCall['messages']);
    }

    public function test_json_helper_decodes_model_output(): void
    {
        $fake = $this->fakeProvider();
        $fake->nextContent = '{"matched": true, "id": 42}';

        $decoded = app(AiGateway::class)->json([AiMessage::user('classify this')]);

        $this->assertSame(['matched' => true, 'id' => 42], $decoded);
        // Json task forces json format on the way out.
        $this->assertSame('json', $fake->lastCall['options']['format']);
    }

    public function test_json_helper_returns_null_on_unparseable_output(): void
    {
        $fake = $this->fakeProvider();
        $fake->nextContent = 'not json at all';

        $this->assertNull(app(AiGateway::class)->json([AiMessage::user('x')]));
    }

    public function test_provider_failure_yields_a_failed_response_not_an_exception(): void
    {
        $throwing = new class implements AIProvider {
            public function name(): string { return 'boom'; }
            public function chat(array $messages, string $model, array $options = []): string
            {
                throw new \RuntimeException('backend down');
            }
            public function stream(array $messages, string $model, array $options = []): \Generator { yield ''; }
            public function vision(string $prompt, string $base64Image, string $model, array $options = []): string { return ''; }
            public function embed(string $text, string $model): ?array { return null; }
            public function listModels(): array { return []; }
            public function isHealthy(): bool { return false; }
        };
        $this->app->instance(AIProvider::class, $throwing);

        $response = app(AiGateway::class)->chat(AiTask::Chat, [AiMessage::user('hi')]);

        $this->assertFalse($response->success);
        $this->assertStringContainsString('backend down', $response->error);
        $this->assertSame('', $response->content);
    }

    public function test_stream_yields_chunks(): void
    {
        $this->fakeProvider();

        $chunks = iterator_to_array(app(AiGateway::class)->stream(AiTask::Chat, [AiMessage::user('go')]));

        $this->assertSame(['chunk-1', 'chunk-2'], $chunks);
    }
}
