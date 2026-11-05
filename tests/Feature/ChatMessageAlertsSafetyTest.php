<?php

namespace Tests\Feature;

use App\Jobs\SendChatMessageAlerts;
use App\Services\WebPushService;
use Tests\TestCase;

class ChatMessageAlertsSafetyTest extends TestCase
{
    public function test_delivery_is_disabled_by_default(): void
    {
        config()->set('chatify-preview.message_notifications_enabled', false);
        $push = $this->mock(WebPushService::class);
        $push->shouldNotReceive('send');

        (new SendChatMessageAlerts(2, 1, '10', '20'))->handle($push);

        $this->assertTrue(true);
    }

    public function test_sender_never_receives_their_own_alert(): void
    {
        config()->set('chatify-preview.message_notifications_enabled', true);
        $push = $this->mock(WebPushService::class);
        $push->shouldNotReceive('send');

        (new SendChatMessageAlerts(1, 1, '10', '20'))->handle($push);

        $this->assertTrue(true);
    }
}
