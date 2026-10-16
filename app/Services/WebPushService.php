<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\WebPushSubscription;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Cache;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\VAPID;
use Minishlink\WebPush\WebPush;

class WebPushService
{
    public function keys(): ?array
    {
        $stored = Setting::get('web_push_vapid');
        if (! $stored) return null;
        return json_decode(Crypt::decryptString($stored), true, 512, JSON_THROW_ON_ERROR);
    }

    /** Stable server-generated keys; deployment never rotates existing subscriptions. */
    public function setup(): void
    {
        Cache::lock('web-push-key-setup', 30)->block(10, function () {
            if ($this->keys()) return;
            Setting::set('web_push_vapid', Crypt::encryptString(json_encode(VAPID::createVapidKeys(), JSON_THROW_ON_ERROR)));
        });
    }

    public function publicKey(): ?string { return $this->keys()['publicKey'] ?? null; }

    public function send(WebPushSubscription $device, array $payload): bool
    {
        $keys = $this->keys();
        if (! $keys) return false;
        $client = new WebPush(['VAPID' => ['subject' => config('app.url'), 'publicKey' => $keys['publicKey'], 'privateKey' => $keys['privateKey']]], ['TTL' => 3600, 'urgency' => 'normal'], 10);
        $report = $client->sendOneNotification(Subscription::create([
            'endpoint' => $device->endpoint,
            'publicKey' => $device->public_key,
            'authToken' => $device->auth_token,
            'contentEncoding' => $device->content_encoding,
        ]), json_encode($payload, JSON_THROW_ON_ERROR));
        if ($report->isSubscriptionExpired()) { $device->delete(); return false; }
        if (! $report->isSuccess()) throw new \RuntimeException('The push provider did not accept this notification.');
        return $report->isSuccess();
    }

    /** Only browser push providers: a submitted endpoint must not become an SSRF target. */
    public static function validEndpoint(string $url): bool
    {
        $parts = parse_url($url);
        if (! $parts || ($parts['scheme'] ?? '') !== 'https' || isset($parts['user']) || isset($parts['pass']) || (isset($parts['port']) && $parts['port'] !== 443)) return false;
        $host = strtolower($parts['host'] ?? '');
        foreach (['fcm.googleapis.com', 'push.services.mozilla.com', 'push.apple.com', 'notify.windows.com', 'wns.windows.com'] as $allowed) {
            if ($host === $allowed || str_ends_with($host, '.'.$allowed)) return true;
        }
        return false;
    }
}
