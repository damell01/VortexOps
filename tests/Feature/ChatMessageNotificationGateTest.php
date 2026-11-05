<?php

namespace Tests\Feature;

use App\Jobs\SendChatMessageAlerts;
use App\Observers\ChatifyMessageObserver;
use App\Services\WebPushService;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class ChatMessageNotificationGateTest extends TestCase
{
    public function test_observer_does_not_queue_alerts_when_messaging_is_disabled(): void
    {
        config([
            'chatify-preview.enabled' => false,
            'chatify-preview.team_enabled' => false,
            'chatify-preview.message_notifications_enabled' => true,
        ]);
        Queue::fake();
        (new ChatifyMessageObserver())->created((object) ['user_id' => 1]);
        Queue::assertNotPushed(SendChatMessageAlerts::class);
    }

    public function test_queued_alert_does_not_deliver_after_rollout_is_disabled(): void
    {
        config([
            'chatify-preview.enabled' => false,
            'chatify-preview.team_enabled' => false,
            'chatify-preview.message_notifications_enabled' => true,
        ]);
        $push = Mockery::mock(WebPushService::class);
        $push->shouldNotReceive('send');
        (new SendChatMessageAlerts(2, 1, 'conversation', 'message'))->handle($push);
    }
}
