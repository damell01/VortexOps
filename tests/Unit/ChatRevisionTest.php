<?php

namespace Tests\Unit;

use App\Support\ChatRevision;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ChatRevisionTest extends TestCase
{
    public function test_touch_changes_the_token_without_storing_message_content(): void
    {
        Cache::flush();
        $id = 'conversation-revision-unit-test';

        $this->assertSame('0', ChatRevision::current($id));
        ChatRevision::touch($id);
        $first = ChatRevision::current($id);
        $this->assertNotSame('0', $first);
        $this->assertSame(24, strlen($first));

        ChatRevision::touch($id);
        $this->assertNotSame($first, ChatRevision::current($id));
    }

    public function test_empty_conversation_id_does_not_write_to_cache(): void
    {
        Cache::flush();
        ChatRevision::touch('');
        $this->assertSame('0', ChatRevision::current(''));
    }
}
