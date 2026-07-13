<?php

namespace Tests\Feature\AI;

use App\AI\DTOs\AiMessage;
use App\AI\Memory\ConversationMemory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversationMemoryTest extends TestCase
{
    use RefreshDatabase;

    private function memory(): ConversationMemory
    {
        return app(ConversationMemory::class);
    }

    public function test_records_and_recalls_turns_in_order(): void
    {
        $mem = $this->memory();
        $key = $mem->key(null, 'test');

        $mem->record($key, AiMessage::user('hello'));
        $mem->record($key, AiMessage::assistant('hi there'));

        $recalled = $mem->recall($key);

        $this->assertSame(
            [['role' => 'user', 'content' => 'hello'], ['role' => 'assistant', 'content' => 'hi there']],
            $recalled,
        );
    }

    public function test_caps_the_number_of_turns_kept(): void
    {
        config()->set('ai.memory.turns', 4);
        $mem = $this->memory();
        $key = $mem->key(null, 'cap');

        for ($i = 1; $i <= 10; $i++) {
            $mem->record($key, AiMessage::user("m{$i}"));
        }

        $recalled = $mem->recall($key);

        $this->assertCount(4, $recalled);
        $this->assertSame('m7', $recalled[0]['content']); // oldest kept
        $this->assertSame('m10', $recalled[3]['content']); // newest
    }

    public function test_recall_respects_an_explicit_limit(): void
    {
        $mem = $this->memory();
        $key = $mem->key(null, 'limit');

        foreach (['a', 'b', 'c'] as $c) {
            $mem->record($key, AiMessage::user($c));
        }

        $this->assertSame([['role' => 'user', 'content' => 'c']], $mem->recall($key, 1));
    }

    public function test_forget_clears_a_conversation(): void
    {
        $mem = $this->memory();
        $key = $mem->key(null, 'wipe');
        $mem->record($key, AiMessage::user('remember me'));

        $mem->forget($key);

        $this->assertSame([], $mem->recall($key));
    }

    public function test_different_users_have_separate_memory(): void
    {
        $mem = $this->memory();
        $a = User::factory()->create();
        $b = User::factory()->create();

        $this->assertNotSame($mem->key($a), $mem->key($b));
    }
}
