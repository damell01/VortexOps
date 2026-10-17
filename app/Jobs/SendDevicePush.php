<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\WebPushSubscription;
use App\Services\WebPushService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendDevicePush implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;
    public int $timeout = 90;
    public int $backoff = 30;

    public function __construct(public int $userId, public string $notificationId, public ?int $deviceId = null) {}

    public function handle(WebPushService $push): void
    {
        $user = User::find($this->userId);
        if (! $user || $user->notifications_enabled === false || $user->notification_in_app_enabled === false) return;
        // A queued alert must still belong to this person and remain unread.
        $notification = $user->notifications()->whereKey($this->notificationId)->whereNull('read_at')->first();
        if (! $notification && $this->deviceId === null) return;
        $url = '/admin';
        if ($notification) {
            $candidate = collect($notification->data['actions'] ?? [])->pluck('url')->filter()->first();
            if (is_string($candidate)) {
                $base = parse_url(config('app.url'));
                $target = parse_url($candidate);
                if (! str_starts_with($candidate, '//') && (($target['host'] ?? null) === null || (($target['host'] ?? '') === ($base['host'] ?? '') && ($target['scheme'] ?? '') === ($base['scheme'] ?? '')))) {
                    $url = $candidate;
                }
            }
        }
        $devices = WebPushSubscription::where('user_id', $user->id)->when($this->deviceId, fn ($q) => $q->whereKey($this->deviceId))->limit(8)->get();
        // The notification itself, as it reads in the bell: its title and its text.
        $plain = fn ($text, int $max) => \Illuminate\Support\Str::limit(trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags(str_replace(['**', '__'], '', (string) $text))))), $max, '…');
        $title = $notification ? $plain($notification->data['title'] ?? '', 80) : '';
        $body = $notification ? $plain($notification->data['body'] ?? '', 180) : '';
        foreach ($devices as $device) {
            $push->send($device, [
                'title' => $this->deviceId ? 'Vortex Ops' : ($title !== '' ? $title : 'Vortex Ops'),
                'body' => $this->deviceId ? 'Push notifications are working on this device.' : ($body !== '' ? $body : ($title !== '' ? 'Open Vortex Ops to see it.' : 'You have a new alert. Open Vortex Ops to view it.')),
                'tag' => 'vortex-'.$this->notificationId,
                'url' => $url,
            ]);
        }
    }
}
