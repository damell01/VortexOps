<?php

namespace Tests\Feature\Communications;

use App\Models\User;
use Chatify\Services\ConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;
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
        Role::findOrCreate('admin', 'web');
        $stranger = User::factory()->create(['email' => 'stranger@example.test']);
        $stranger->assignRole('admin');
        $thread = app(ConversationService::class)->findOrCreateDirect($owner, $other);
        $url = '/admin/communications/conversations/'.$thread->getKey().'/revision';

        $this->actingAs($owner)->getJson($url)->assertOk()->assertJsonPath('revision', '0');
        $this->actingAs($stranger)->getJson($url)->assertNotFound();
    }

    public function test_revision_endpoint_reflects_changes_without_exposing_messages(): void
    {
        $owner = User::factory()->create(['email' => config('app.owner_email')]);
        $other = User::factory()->create();
        $thread = app(ConversationService::class)->findOrCreateDirect($owner, $other);
        $id = (string) $thread->getKey();
        $url = '/admin/communications/conversations/'.$id.'/revision';

        $first = $this->actingAs($owner)->getJson($url)->assertOk()->json('revision');
        Cache::put('chat:revision:'.hash('sha256', $id), 'changed-once', now()->addDay());
        $second = $this->getJson($url)->assertOk()->json('revision');
        $this->assertNotSame($first, $second);

        Cache::put('chat:revision:'.hash('sha256', $id), 'changed-again', now()->addDay());
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
