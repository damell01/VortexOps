<?php

namespace Tests\Feature\AI;

use App\Services\AI\OllamaClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Regression coverage for a bug where array_filter()'s default callback
 * silently dropped 'stream' => false from the request payload (false is
 * falsy), causing Ollama to stream its response instead of returning one
 * JSON document — generate()/chat() then always returned empty content
 * despite a 200 response, since json_decode() can't parse a multi-line
 * NDJSON stream as a single object.
 */
class OllamaClientTest extends TestCase
{
    use RefreshDatabase;

    private function client(): OllamaClient
    {
        return new OllamaClient('http://localhost:11434', 'llama3.2:3b', 'nomic-embed-text', 'moondream');
    }

    public function test_generate_sends_stream_false_and_parses_response(): void
    {
        Http::fake(['*/api/generate' => Http::response(['response' => 'Hello there.'])]);

        $content = $this->client()->generate('Say hi');

        $this->assertSame('Hello there.', $content);
        Http::assertSent(fn ($request) => $request->data()['stream'] === false);
    }

    public function test_chat_sends_stream_false_and_parses_message_content(): void
    {
        Http::fake(['*/api/chat' => Http::response(['message' => ['content' => 'Hi from the model.']])]);

        $content = $this->client()->chat([['role' => 'user', 'content' => 'hi']]);

        $this->assertSame('Hi from the model.', $content);
        Http::assertSent(fn ($request) => $request->data()['stream'] === false);
    }

    public function test_chat_omits_null_options_but_keeps_stream_false(): void
    {
        Http::fake(['*/api/chat' => Http::response(['message' => ['content' => 'ok']])]);

        $this->client()->chat([['role' => 'user', 'content' => 'hi']]);

        Http::assertSent(function ($request) {
            $body = $request->data();

            return $body['stream'] === false
                && ! array_key_exists('format', $body)
                && ! array_key_exists('options', $body);
        });
    }

    public function test_generate_forwards_format_when_given(): void
    {
        Http::fake(['*/api/generate' => Http::response(['response' => '{}'])]);

        $this->client()->generate('Say hi', ['format' => 'json']);

        Http::assertSent(fn ($request) => $request->data()['format'] === 'json' && $request->data()['stream'] === false);
    }
}
