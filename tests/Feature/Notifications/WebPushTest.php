<?php

namespace Tests\Feature\Notifications;

use App\Jobs\SendDevicePush;
use App\Listeners\QueueDevicePush;
use App\Models\Setting;
use App\Models\User;
use App\Models\WebPushSubscription;
use App\Services\WebPushService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Minishlink\WebPush\VAPID;
use Tests\TestCase;

class WebPushTest extends TestCase
{
    use RefreshDatabase;

    private function subscription(): array
    {
        return ['endpoint' => 'https://fcm.googleapis.com/fcm/send/test-device', 'keys' => ['p256dh' => VAPID::createVapidKeys()['publicKey'], 'auth' => rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=')]];
    }

    private function user(): User
    {
        $user = User::factory()->create(['notifications_enabled' => true, 'notification_in_app_enabled' => true]);
        $this->actingAs($user);
        app(WebPushService::class)->setup();
        return $user;
    }

    public function test_setup_is_idempotent_and_never_returns_the_private_key(): void
    {
        $this->user();
        $keys = app(WebPushService::class)->keys();
        $this->artisan('push:setup')->assertSuccessful();
        $this->assertSame($keys, app(WebPushService::class)->keys());
        $this->getJson(route('push.config'))->assertOk()->assertExactJson(['public_key' => $keys['publicKey']]);
        $this->assertStringNotContainsString($keys['privateKey'], Setting::where('key', 'web_push_vapid')->value('value'));
    }

    public function test_device_can_opt_in_check_status_and_opt_out_with_encrypted_storage(): void
    {
        $user = $this->user(); $data = $this->subscription();
        $this->postJson(route('push.store'), $data)->assertOk()->assertJson(['enabled' => true]);
        $device = WebPushSubscription::firstOrFail();
        $this->assertSame($user->id, $device->user_id);
        $this->assertSame($data['endpoint'], $device->endpoint);
        $this->assertNotSame($data['endpoint'], $device->getRawOriginal('endpoint'));
        $this->postJson(route('push.status'), ['endpoint' => $data['endpoint']])->assertOk()->assertJson(['enabled' => true]);
        $this->deleteJson(route('push.destroy'), ['endpoint' => $data['endpoint']])->assertOk();
        $this->assertDatabaseCount('web_push_subscriptions', 0);
    }

    public function test_another_user_cannot_check_delete_or_test_someone_elses_subscription(): void
    {
        $this->user(); $data = $this->subscription();
        $this->postJson(route('push.store'), $data)->assertOk();
        $this->actingAs(User::factory()->create());
        $this->postJson(route('push.status'), ['endpoint' => $data['endpoint']])->assertJson(['enabled' => false]);
        $this->deleteJson(route('push.destroy'), ['endpoint' => $data['endpoint']])->assertOk();
        $this->assertDatabaseCount('web_push_subscriptions', 1);
        $this->postJson(route('push.test'), ['endpoint' => $data['endpoint']])->assertNotFound();
    }

    public function test_private_network_and_invalid_keys_are_rejected(): void
    {
        $this->user(); $data = $this->subscription();
        foreach (['https://127.0.0.1/private', 'http://fcm.googleapis.com/test', 'https://fcm.googleapis.com.attacker.test/push', 'https://user:pass@fcm.googleapis.com/test'] as $url) {
            $this->postJson(route('push.store'), array_replace($data, ['endpoint' => $url]))->assertUnprocessable();
        }
        $data['keys']['p256dh'] = str_repeat('A', 87);
        $this->postJson(route('push.store'), $data)->assertUnprocessable();
        $this->assertDatabaseCount('web_push_subscriptions', 0);
    }

    public function test_only_routed_database_alerts_for_opted_in_devices_queue_push(): void
    {
        Queue::fake(); $user = $this->user(); $data = $this->subscription();
        $notice = $user->notifications()->create(['id' => (string) Str::uuid(), 'type' => 'test', 'data' => ['title' => 'Private payroll amount $1000']]);
        $event = new NotificationSent($user, new PushTestNotification, 'database', $notice);
        app(QueueDevicePush::class)->handle($event);
        Queue::assertNothingPushed();
        $this->postJson(route('push.store'), $data)->assertOk();
        app(QueueDevicePush::class)->handle($event);
        Queue::assertPushed(SendDevicePush::class, fn ($job) => $job->userId === $user->id && $job->notificationId === $notice->id);
    }

    public function test_delivery_rechecks_preferences_and_shows_the_notification_itself(): void
    {
        $user = $this->user(); $data = $this->subscription();
        $this->postJson(route('push.store'), $data)->assertOk();
        $notice = $user->notifications()->create(['id' => (string) Str::uuid(), 'type' => 'test', 'data' => ['title' => 'Show report approved', 'body' => '**Friday Breaks** was <b>approved</b>.']]);
        $push = $this->mock(WebPushService::class);
        $push->shouldReceive('send')->once()->withArgs(fn ($device, $payload) => $device->user_id === $user->id
            && $payload['title'] === 'Show report approved' && $payload['body'] === 'Friday Breaks was approved.')->andReturnTrue();
        (new SendDevicePush($user->id, $notice->id))->handle($push);
        $user->update(['notifications_enabled' => false]);
        (new SendDevicePush($user->id, $notice->id))->handle($push);
    }

    public function test_test_notification_targets_only_the_current_device(): void
    {
        Queue::fake(); $user = $this->user(); $data = $this->subscription();
        $this->postJson(route('push.store'), $data)->assertOk();
        $this->postJson(route('push.test'), ['endpoint' => $data['endpoint']])->assertOk()->assertJson(['queued' => true]);
        Queue::assertPushed(SendDevicePush::class, fn ($job) => $job->userId === $user->id && $job->deviceId === WebPushSubscription::first()->id);
    }

    public function test_guests_cannot_manage_push_devices(): void
    {
        $this->getJson(route('push.config'))->assertUnauthorized();
        $this->postJson(route('push.store'), $this->subscription())->assertUnauthorized();
    }
}

class PushTestNotification extends Notification {}
