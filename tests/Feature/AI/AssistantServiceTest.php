<?php

namespace Tests\Feature\AI;

use App\AI\Contracts\AIProvider;
use App\AI\Services\AssistantService;
use App\Models\Streamer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssistantServiceTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->create(['email' => config('app.owner_email', 'dbellcreations@gmail.com')]);
    }

    /**
     * A provider that returns a scripted classifier decision on the FIRST chat
     * call (intent routing, json mode) and a canned grounded answer thereafter.
     */
    private function scriptedProvider(string $classifierJson, string $groundedAnswer): object
    {
        $fake = new class($classifierJson, $groundedAnswer) implements AIProvider {
            public int $calls = 0;
            public array $groundedMessages = [];
            public function __construct(private string $classifierJson, private string $groundedAnswer) {}
            public function name(): string { return 'fake'; }
            public function chat(array $messages, string $model, array $options = []): string
            {
                $this->calls++;
                // First call is the intent classifier (json format requested).
                if (($options['format'] ?? null) === 'json') {
                    return $this->classifierJson;
                }
                $this->groundedMessages = $messages;
                return $this->groundedAnswer;
            }
            public function stream(array $messages, string $model, array $options = []): \Generator { yield $this->groundedAnswer; }
            public function vision(string $prompt, string $base64Image, string $model, array $options = []): string { return ''; }
            public function embed(string $text, string $model): ?array { return null; }
            public function listModels(): array { return []; }
            public function isHealthy(): bool { return true; }
        };
        $this->app->instance(AIProvider::class, $fake);

        return $fake;
    }

    public function test_answer_runs_the_tool_and_grounds_the_reply(): void
    {
        Streamer::create(['name' => 'Jordan Breaks', 'status' => 'active', 'total_earnings_due' => 300, 'total_earnings_paid' => 120]);

        $fake = $this->scriptedProvider(
            classifierJson: '{"tool": "streamer.balance", "arguments": {"name": "Jordan"}, "confidence": 0.95}',
            groundedAnswer: 'Jordan Breaks is owed $180.00.',
        );

        $result = app(AssistantService::class)->answer('/admin', 'what do we owe jordan?', $this->owner());

        $this->assertTrue($result['success']);
        $this->assertSame('streamer.balance', $result['tool']);
        $this->assertSame(180.0, $result['data']['outstanding']);
        $this->assertStringContainsString('180.00', $result['content']);
        // The grounding prompt carried the real tool summary into the model.
        $grounded = collect($fake->groundedMessages)->firstWhere('role', 'user')['content'] ?? '';
        $this->assertStringContainsString('Jordan Breaks is owed $180.00', $grounded);
    }

    public function test_answer_falls_back_to_plain_chat_when_no_tool_fits(): void
    {
        $this->scriptedProvider(
            classifierJson: '{"tool": null, "arguments": {}, "confidence": 0.0}',
            groundedAnswer: 'Hello! How can I help?',
        );

        $result = app(AssistantService::class)->answer('/admin', 'hi there', $this->owner());

        $this->assertTrue($result['success']);
        $this->assertNull($result['tool']);
        $this->assertSame([], $result['data']);
    }

    public function test_stream_returns_chunks(): void
    {
        $this->scriptedProvider(
            classifierJson: '{"tool": null, "arguments": {}, "confidence": 0.0}',
            groundedAnswer: 'streamed hello',
        );

        $chunks = iterator_to_array(app(AssistantService::class)->stream('/admin', [], 'hi', $this->owner()));

        $this->assertNotEmpty($chunks);
    }
}
