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

        $chunks = iterator_to_array(app(AssistantService::class)->stream('/admin', 'hi', $this->owner()));

        $this->assertNotEmpty($chunks);
    }

    public function test_second_turn_recalls_the_first_from_memory(): void
    {
        // A provider that classifies everything as plain chat and records the
        // messages its stream() was handed, so we can see what history reached it.
        $fake = new class implements AIProvider {
            public array $lastStreamMessages = [];
            public function name(): string { return 'fake'; }
            public function chat(array $messages, string $model, array $options = []): string
            {
                return '{"tool": null, "arguments": {}, "confidence": 0.0}'; // never routes a tool
            }
            public function stream(array $messages, string $model, array $options = []): \Generator
            {
                $this->lastStreamMessages = $messages;
                yield 'ok';
            }
            public function vision(string $prompt, string $base64Image, string $model, array $options = []): string { return ''; }
            public function embed(string $text, string $model): ?array { return null; }
            public function listModels(): array { return []; }
            public function isHealthy(): bool { return true; }
        };
        $this->app->instance(AIProvider::class, $fake);

        $owner = $this->owner();
        $svc   = app(AssistantService::class);

        // Turn 1 — must be fully consumed so the reply is recorded to memory.
        iterator_to_array($svc->stream('/admin', 'my name is Sam', $owner));
        // Turn 2 — history should now carry turn 1.
        iterator_to_array($svc->stream('/admin', 'what is my name?', $owner));

        $contents = array_column($fake->lastStreamMessages, 'content');
        $joined   = implode("\n", $contents);

        $this->assertStringContainsString('my name is Sam', $joined, 'prior user turn should be recalled');
        $this->assertStringContainsString('ok', $joined, 'prior assistant reply should be recalled');
        $this->assertStringContainsString('what is my name?', $joined, 'current turn should be present');
    }

    public function test_forget_wipes_memory(): void
    {
        $this->scriptedProvider('{"tool": null, "arguments": {}, "confidence": 0.0}', 'ok');

        $owner = $this->owner();
        $mem   = app(\App\AI\Memory\ConversationMemory::class);
        $svc   = app(AssistantService::class);

        iterator_to_array($svc->stream('/admin', 'remember this', $owner));
        $this->assertNotEmpty($mem->recall($mem->key($owner)));

        $svc->forget($owner);
        $this->assertSame([], $mem->recall($mem->key($owner)));
    }
}
