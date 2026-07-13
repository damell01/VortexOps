<?php

namespace Tests\Feature\AI;

use App\AI\Contracts\AIProvider;
use App\AI\Services\IntentRouter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntentRouterTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->create(['email' => config('app.owner_email', 'dbellcreations@gmail.com')]);
    }

    /** Bind a provider whose chat() returns a fixed JSON decision. */
    private function decidingProvider(string $json): void
    {
        $fake = new class($json) implements AIProvider {
            public function __construct(private string $json) {}
            public function name(): string { return 'fake'; }
            public function chat(array $messages, string $model, array $options = []): string { return $this->json; }
            public function stream(array $messages, string $model, array $options = []): \Generator { yield ''; }
            public function vision(string $prompt, string $base64Image, string $model, array $options = []): string { return ''; }
            public function embed(string $text, string $model): ?array { return null; }
            public function listModels(): array { return []; }
            public function isHealthy(): bool { return true; }
        };
        $this->app->instance(AIProvider::class, $fake);
    }

    public function test_routes_a_clear_message_to_the_named_tool(): void
    {
        $this->decidingProvider('{"tool": "inventory.lookup", "arguments": {"query": "Prizm"}, "confidence": 0.9}');

        $resolution = app(IntentRouter::class)->route('how many prizm do we have', $this->owner());

        $this->assertTrue($resolution->isTool());
        $this->assertSame('inventory.lookup', $resolution->tool);
        $this->assertSame('Prizm', $resolution->arguments['query']);
    }

    public function test_falls_back_to_chat_when_no_tool_chosen(): void
    {
        $this->decidingProvider('{"tool": null, "arguments": {}, "confidence": 0.0}');

        $resolution = app(IntentRouter::class)->route('hello there', $this->owner());

        $this->assertFalse($resolution->isTool());
        $this->assertSame('chat', $resolution->type);
    }

    public function test_low_confidence_falls_back_to_chat(): void
    {
        $this->decidingProvider('{"tool": "inventory.lookup", "arguments": {"query": "x"}, "confidence": 0.2}');

        $this->assertFalse(app(IntentRouter::class)->route('maybe inventory?', $this->owner())->isTool());
    }

    public function test_unknown_tool_name_falls_back_to_chat(): void
    {
        $this->decidingProvider('{"tool": "does.not.exist", "arguments": {}, "confidence": 1.0}');

        $this->assertFalse(app(IntentRouter::class)->route('do the thing', $this->owner())->isTool());
    }

    public function test_unauthorized_user_gets_chat_with_no_tools(): void
    {
        // No provider call should even happen — empty catalog short-circuits.
        $stranger = User::factory()->create(['email' => 'nobody@example.com']);

        $resolution = app(IntentRouter::class)->route('how many prizm do we have', $stranger);

        $this->assertFalse($resolution->isTool());
    }
}
