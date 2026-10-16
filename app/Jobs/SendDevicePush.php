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
        foreach ($devices as $device) {
            // Avoid financial or personal details on shared lock screens.
            $push->send($device, ['title' => 'Vortex Ops', 'body' => $this->deviceId ? 'Push notifications are working on this device.' : 'You have a new alert. Open Vortex Ops to view it.', 'tag' => 'vortex-'.$this->notificationId, 'url' => $url]);
        }
    }
}
