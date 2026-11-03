<?php

namespace App\Http\Controllers;

use App\Models\WhatnotApiConnection;
use App\Models\WhatnotChannel;
use App\Services\WhatnotApiHealth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatnotApiHealthController extends Controller
{
    private function authorizeOwner(Request $request): void
    {
        abort_unless($request->user()?->isOwner(), 403);
    }

    public function dashboard(Request $request, WhatnotApiHealth $health): \Illuminate\View\View
    {
        $this->authorizeOwner($request);
        return view('admin.whatnot-api-health', [
            'connections' => $health->overview(),
            'apiFlags' => [
                'live_requests' => (bool) config('services.whatnot_seller.live_requests_enabled', false),
                'contract_verified' => (bool) config('services.whatnot_seller.contract_verified', false),
                'pagination_verified' => (bool) config('services.whatnot_seller.pagination_verified', false),
                'scheduler_enabled' => (bool) config('services.whatnot_seller.sync_scheduler_enabled', false),
                'apply_enabled' => (bool) config('services.whatnot_seller.apply_enabled', false),
                'ledger_start' => (string) config('services.whatnot_seller.ledger_start', ''),
            ],
        ]);
    }

    public function index(Request $request, WhatnotApiHealth $health): JsonResponse
    {
        $this->authorizeOwner($request);
        return response()->json(['connections' => $health->overview(), 'scraper_unchanged' => true]);
    }

    public function save(Request $request, WhatnotChannel $channel): JsonResponse
    {
        $this->authorizeOwner($request);
        $data = $request->validate(['token' => ['nullable', 'string', 'max:8192']]);
        $connection = WhatnotApiConnection::firstOrNew(['whatnot_channel_id' => $channel->id]);
        if (filled($data['token'] ?? null)) {
            $connection->access_token = $data['token'];
            $connection->status = 'pending_test';
            $connection->last_success_at = null;
            $connection->last_checked_at = null;
            $connection->last_error = null;
        }
        $connection->enabled = false;
        $connection->sync_enabled = false;
        $connection->save();

        return response()->json(['saved' => true, 'configured' => filled($connection->access_token)]);
    }

    public function test(Request $request, WhatnotChannel $channel, WhatnotApiHealth $health): JsonResponse
    {
        $this->authorizeOwner($request);
        $connection = WhatnotApiConnection::where('whatnot_channel_id', $channel->id)->first();
        if (! $connection || blank($connection->access_token)) {
            return $this->noTokenYet();
        }
        return response()->json($health->check($connection));
    }
    /** Read-only comparison of the API with VortexOps (WhatnotApiVerifier). */
    public function verify(Request $request, WhatnotChannel $channel, \App\Services\WhatnotApiVerifier $verifier): JsonResponse
    {
        $this->authorizeOwner($request);
        $connection = WhatnotApiConnection::where('whatnot_channel_id', $channel->id)->first();
        if (! $connection || blank($connection->access_token)) {
            return $this->noTokenYet();
        }
        if (! app()->environment('production') || ! config('services.whatnot_seller.live_requests_enabled', false)) {
            return response()->json(['ok' => false, 'message' => 'Live API checks are disabled in this environment'], 422);
        }
        @set_time_limit(300);
        $report = $verifier->verify($connection, 7);

        return response()->json(['ok' => $report['problems'] === [], 'message' => $report['verdict'].($report['problems'] ? ' '.implode(' ', $report['problems']) : ''), 'report' => $report]);
    }

    /** No token saved for this channel yet: say so, rather than a bare 404. */
    private function noTokenYet(): JsonResponse
    {
        return response()->json(['ok' => false, 'enabled' => false, 'message' => 'No API token is saved for this channel yet. Paste one and press Save token first.'], 422);
    }

    public function toggle(Request $request, WhatnotChannel $channel): JsonResponse
    {
        $this->authorizeOwner($request);
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'confirmation' => ['nullable', 'string'],
        ]);
        $connection = WhatnotApiConnection::where('whatnot_channel_id', $channel->id)->first();
        if (! $connection) {
            return $this->noTokenYet();
        }
        if ($data['enabled']) {
            if (! app()->environment('production')
                || ! config('services.whatnot_seller.live_requests_enabled', false)
                || ! config('services.whatnot_seller.contract_verified', false)
                || ! config('services.whatnot_seller.pagination_verified', false)
                || ! config('services.whatnot_seller.sync_scheduler_enabled', false)
                || $data['confirmation'] !== 'ENABLE READ ONLY'
                || blank($connection->access_token)
                || $connection->status !== 'connected'
                || ! $connection->last_success_at
                || $connection->last_success_at->lt(now()->subHours(24))) {
                return response()->json(['enabled' => false, 'message' => 'Activation blocked: verify production configuration, credentials and a recent successful connection test.'], 422);
            }
        }
        $connection->forceFill([
            'enabled' => (bool) $data['enabled'],
            'sync_enabled' => (bool) $data['enabled'],
        ])->save();
        return response()->json(['enabled' => (bool) $connection->sync_enabled,
            'message' => $connection->sync_enabled ? 'Read-only snapshot capture enabled for this channel.' : 'API capture disabled for this channel.']);
    }

}
