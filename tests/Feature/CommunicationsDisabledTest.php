<?php

namespace Tests\Feature;

use App\Filament\Pages\Communications;
use App\Jobs\SendChatMessageAlerts;
use App\Livewire\AnnouncementPreview;
use App\Livewire\QuickMessages;
use App\Models\User;
use App\Observers\ChatifyMessageObserver;
use App\Services\TeamCommunications;
use App\Services\WebPushService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class CommunicationsDisabledTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config([
            'chatify-preview.available' => false,
            // Old rollout settings must not override the master switch.
            'chatify-preview.enabled' => true,
            'chatify-preview.team_enabled' => true,
            'chatify-preview.message_notifications_enabled' => true,
        ]);
        $this->actingAs(User::factory()->create(['email' => config('app.owner_email')]));
    }

    public function test_owner_cannot_open_messages_announcements_or_package_endpoints(): void
    {
        $this->assertFalse(TeamCommunications::canAccess(auth()->user()));
        $this->assertFalse(Communications::canAccess());
        foreach (['/admin/communications', '/admin/communications/group-roles', '/admin/communications/reactions/example', '/chatify', '/api/chatify/v1/conversations'] as $url) {
            $this->getJson($url)->assertNotFound();
        }
        $this->postJson('/api/chatify/v1/conversations', ['type' => 'direct'])->assertNotFound();
        $this->postJson('/admin/communications/announcements/1/acknowledge')->assertNotFound();
        $this->postJson('/admin/communications/reactions/example', ['emoji' => '👍'])->assertNotFound();
    }

    public function test_livewire_components_are_unavailable_even_to_owner(): void
    {
        Livewire::test(QuickMessages::class)->assertForbidden();
        Livewire::test(AnnouncementPreview::class)->assertForbidden();
        Livewire::test(Communications::class)->assertForbidden();
    }

    public function test_widget_and_queued_delivery_stay_off(): void
    {
        $this->assertStringNotContainsString('vx-chatify-shell', view('filament.components.global-communications-widget')->render());
        Queue::fake();
        (new ChatifyMessageObserver())->created((object) ['user_id' => 1]);
        Queue::assertNotPushed(SendChatMessageAlerts::class);
        $push = $this->mock(WebPushService::class);
        $push->shouldNotReceive('send');
        (new SendChatMessageAlerts(2, 1, 'conversation', 'message'))->handle($push);
    }
}
