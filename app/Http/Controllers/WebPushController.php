<?php

namespace App\Http\Controllers;

use App\Jobs\SendDevicePush;
use App\Models\WebPushSubscription;
use App\Services\WebPushService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WebPushController extends Controller
{
    public function config(WebPushService $push)
    {
        return response()->json(['public_key' => $push->publicKey()])->header('Cache-Control', 'private, no-store');
    }

    private function endpoint(Request $request): string
    {
        $data = $request->validate(['endpoint' => ['bail', 'required', 'string', 'max:4096', fn ($attribute, $value, $fail) => WebPushService::validEndpoint($value) ?: $fail('This browser push endpoint is not supported.')]]);
        return $data['endpoint'];
    }

    public function status(Request $request)
    {
        $endpoint = $this->endpoint($request);
        $hash = hash('sha256', $endpoint);
        $enabled = WebPushSubscription::where('user_id', $request->user()->id)->where('endpoint_hash', $hash)->exists();
        $response = response()->json(['enabled' => $enabled])->header('Cache-Control', 'private, no-store');
        if ($enabled) $response->cookie('vx_push_device', $hash, 525600, '/', null, true, true, false, 'lax');
        return $response;
    }

    public function store(Request $request, WebPushService $push)
    {
        abort_unless($push->publicKey(), 503, 'Push is not configured on this server yet.');
        $endpoint = $this->endpoint($request);
        $data = $request->validate(['keys.p256dh' => 'required|string|regex:/^[A-Za-z0-9_-]{87}=?$/', 'keys.auth' => 'required|string|regex:/^[A-Za-z0-9_-]{22}={0,2}$/']);
        $decode = fn ($value) => base64_decode(strtr($value, '-_', '+/'), true);
        $public = $decode($data['keys']['p256dh']);
        abort_unless(is_string($public) && strlen($public) === 65 && ord($public[0]) === 4 && strlen($decode($data['keys']['auth']) ?: '') === 16, 422, 'Invalid browser subscription keys.');
        $hash = hash('sha256', $endpoint);
        abort_if(WebPushSubscription::where('user_id', $request->user()->id)->where('endpoint_hash', '!=', $hash)->count() >= 8, 422, 'Eight devices are already enabled for this account.');
        WebPushSubscription::updateOrCreate(['endpoint_hash' => $hash], ['user_id' => $request->user()->id, 'endpoint' => $endpoint, 'public_key' => $data['keys']['p256dh'], 'auth_token' => $data['keys']['auth'], 'content_encoding' => 'aes128gcm']);
        $request->session()->put('push_endpoint_hash', $hash);
        return response()->json(['enabled' => true])->cookie('vx_push_device', $hash, 525600, '/', null, true, true, false, 'lax');
    }

    public function destroy(Request $request)
    {
        $endpoint = $this->endpoint($request);
        WebPushSubscription::where('user_id', $request->user()->id)->where('endpoint_hash', hash('sha256', $endpoint))->delete();
        $request->session()->forget('push_endpoint_hash');
        return response()->json(['enabled' => false])->withoutCookie('vx_push_device');
    }

    public function test(Request $request)
    {
        $endpoint = $this->endpoint($request);
        $device = WebPushSubscription::where('user_id', $request->user()->id)->where('endpoint_hash', hash('sha256', $endpoint))->firstOrFail();
        abort_if($request->user()->notifications_enabled === false || $request->user()->notification_in_app_enabled === false, 422, 'Turn on in-app notifications before sending a test.');
        SendDevicePush::dispatch($request->user()->id, (string) Str::uuid(), $device->id)->afterCommit();
        return response()->json(['queued' => true]);
    }
}
