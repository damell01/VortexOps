<?php

namespace Tests\Feature\Communications;

use App\Models\User;
use App\Support\ChatRevision;
use Chatify\Services\ConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ChatRevisionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! class_exists(\Chatify\Models\Conversation::class)) {
            $this->markTestSkipped('Chatify is not installed');
        }

        config([
            'chatify-preview.available' => true,
            'chatify-preview.enabled' => true,
        ]);
        Cache::flush();
    }

    public function test_only_conversation_members_can_read_the_revision(): void
    {
        $owner = User::factory()->create(['email' => config('app.owner_email')]);
        $other = User::factory()->create();
        $stranger = User::factory()->create(['email' => 'stranger@example.test']);
        $thread = app(ConversationService::class)->findOrCreateDirect($owner, $other);
        $url = '/admin/communications/conversations/'.$thread->getKey().'/revision';

        $this->actingAs($owner)->getJson($url)->assertOk()->assertJsonPath('revision', '0');
        $this->actingAs($stranger)->getJson($url)->assertForbidden();
    }

    public function test_revision_changes_after_a_message_or_reaction_event(): void
    {
        $owner = User::factory()->create(['email' => config('app.owner_email')]);
        $other = User::factory()->create();
        $thread = app(ConversationService::class)->findOrCreateDirect($owner, $other);
        $id = (string) $thread->getKey();
        $url = '/admin/communications/conversations/'.$id.'/revision';

        $first = $this->actingAs($owner)->getJson($url)->assertOk()->json('revision');
        ChatRevision::touch($id);
        $second = $this->getJson($url)->assertOk()->json('revision');
        $this->assertNotSame($first, $second);

        ChatRevision::touch($id);
        $third = $this->getJson($url)->assertOk()->json('revision');
        $this->assertNotSame($second, $third);
    }

    public function test_revision_is_unavailable_when_messaging_is_disabled(): void
    {
        $owner = User::factory()->create(['email' => config('app.owner_email')]);
        $other = User::factory()->create();
        $thread = app(ConversationService::class)->findOrCreateDirect($owner, $other);
        config(['chatify-preview.available' => false, 'chatify-preview.enabled' => false]);

        $this->actingAs($owner)
            ->getJson('/admin/communications/conversations/'.$thread->getKey().'/revision')
            ->assertForbidden();
    }
}
