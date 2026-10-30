<?php

namespace App\Services;

use App\Models\WhatnotApiConnection;
use App\Models\WhatnotChannel;
use Illuminate\Support\Facades\DB;
use Throwable;

class WhatnotApiHealth
{
    public function overview(): array
    {
        return WhatnotChannel::query()->orderBy('id')->get()->map(function (WhatnotChannel $channel): array {
            $connection = WhatnotApiConnection::query()->where('whatnot_channel_id', $channel->id)->first();
            $snapshots = DB::table('whatnot_api_snapshots')
                ->where('whatnot_channel_id', $channel->id)
                ->selectRaw('resource, COUNT(*) as total, MAX(fetched_at) as last_capture')
                ->groupBy('resource')->get()->keyBy('resource');
            $audits = DB::table('whatnot_api_sync_audits')
                ->where('whatnot_channel_id', $channel->id)
                ->orderByDesc('id')->limit(5)->get(['resource', 'status', 'finished_at']);
            return [
                'live_requests_allowed' => app()->environment('production') && (bool) config('services.whatnot_seller.live_requests_enabled', false),
                'snapshots' => collect(['shows', 'orders', 'reports'])->mapWithKeys(fn ($resource) => [$resource => [
                    'count' => (int) ($snapshots->get($resource)?->total ?? 0),
                    'last_capture' => $snapshots->get($resource)?->last_capture,
                ]])->all(),
                'data_health' => [
                    'shows' => $this->freshness($snapshots->get('shows')?->last_capture),
                    'orders' => $this->freshness($snapshots->get('orders')?->last_capture),
                    'reports' => $this->freshness($snapshots->get('reports')?->last_capture),
                ],
                'recent_audits' => $audits->map(fn ($audit) => [
                    'resource' => $audit->resource, 'status' => $audit->status,
                    'finished_at' => $audit->finished_at,
                ])->all(),
                'channel_id' => $channel->id,
                'name' => $channel->name,
                'username' => $channel->whatnot_username,
                'configured' => $connection !== null && filled($connection->access_token),
                'enabled' => (bool) ($connection?->enabled ?? false),
                'sync_enabled' => (bool) ($connection?->sync_enabled ?? false),
                'status' => $connection?->status ?? 'not_configured',
                'last_checked_at' => $connection?->last_checked_at?->toIso8601String(),
                'last_success_at' => $connection?->last_success_at?->toIso8601String(),
                'last_error' => $connection?->last_error,
            ];
        })->all();
    }

    private function freshness(?string $timestamp): string
    {
        if ($timestamp === null) {
            return 'no_data';
        }
        return \Illuminate\Support\Carbon::parse($timestamp)->lt(now()->subHours(24))
            ? 'stale' : 'recent';
    }

    public function check(WhatnotApiConnection $connection): array
    {
        if (! app()->environment('production') || ! config('services.whatnot_seller.live_requests_enabled', false)) {
            return ['ok' => false, 'status' => 'disabled', 'message' => 'Live API checks are disabled in this environment'];
        }
        if (blank($connection->access_token)) {
            return ['ok' => false, 'status' => 'not_configured', 'message' => 'No API token configured'];
        }

        try {
            $response = app(WhatnotSellerApiClient::class)->get($connection, 'shows');

            $status = $response->successful() ? 'connected' : 'error';
            $message = $response->successful() ? null : 'Whatnot returned HTTP '.$response->status();
            $connection->forceFill([
                'status' => $status,
                'last_checked_at' => now(),
                'last_success_at' => $response->successful() ? now() : $connection->last_success_at,
                'last_error' => $message,
            ])->save();

            return ['ok' => $response->successful(), 'status' => $status, 'message' => $message];
        } catch (Throwable $e) {
            $connection->forceFill([
                'status' => 'error',
                'last_checked_at' => now(),
                'last_error' => 'Connection failed: '.$e::class,
            ])->save();
            return ['ok' => false, 'status' => 'error', 'message' => 'Connection failed'];
        }
    }
}
