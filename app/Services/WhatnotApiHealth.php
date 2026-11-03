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
        $webhookStats = DB::table('whatnot_api_webhook_events')
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')->pluck('total', 'status');
        return WhatnotChannel::query()->orderBy('id')->get()->map(function (WhatnotChannel $channel) use ($webhookStats): array {
            $connection = WhatnotApiConnection::query()->where('whatnot_channel_id', $channel->id)->first();
            $snapshots = DB::table('whatnot_api_snapshots')
                ->where('whatnot_channel_id', $channel->id)
                ->selectRaw('resource, COUNT(*) as total, MAX(fetched_at) as last_capture')
                ->groupBy('resource')->get()->keyBy('resource');
            $successfulCaptures = DB::table('whatnot_api_sync_audits')
                ->where('whatnot_channel_id', $channel->id)
                ->where('status', 'captured')
                ->selectRaw('resource, MAX(finished_at) as last_successful_fetch')
                ->groupBy('resource')->get()->keyBy('resource');
            $audits = DB::table('whatnot_api_sync_audits')
                ->where('whatnot_channel_id', $channel->id)
                ->orderByDesc('id')->limit(5)->get(['resource', 'status', 'finished_at', 'summary']);
            return [
                'webhook_inbox' => [
                    'pending' => (int) ($webhookStats['pending'] ?? 0),
                    'staged' => (int) ($webhookStats['staged'] ?? 0),
                    'failed' => (int) ($webhookStats['failed'] ?? 0),
                    'ignored' => (int) ($webhookStats['ignored'] ?? 0),
                ],
                'live_requests_allowed' => app()->environment('production') && (bool) config('services.whatnot_seller.live_requests_enabled', false),
                'snapshots' => collect(['shows', 'orders', 'reports'])->mapWithKeys(fn ($resource) => [$resource => [
                    'count' => (int) ($snapshots->get($resource)?->total ?? 0),
                    'last_capture' => $snapshots->get($resource)?->last_capture,
                    'last_successful_fetch' => $successfulCaptures->get($resource)?->last_successful_fetch,
                ]])->all(),
                'data_health' => [
                    'shows' => $this->freshness($successfulCaptures->get('shows')?->last_successful_fetch),
                    'orders' => $this->freshness($successfulCaptures->get('orders')?->last_successful_fetch),
                    'reports' => $this->freshness($successfulCaptures->get('reports')?->last_successful_fetch),
                ],
                'recent_audits' => $audits->map(fn ($audit) => [
                    'resource' => $audit->resource, 'status' => $audit->status,
                    'finished_at' => $audit->finished_at,
                    'detail' => $audit->resource === 'orders_apply'
                        ? (function () use ($audit) {
                            $c = (json_decode((string) $audit->summary, true)['counts'] ?? []);
                            return ($c['created'] ?? 0).' new, '.($c['updated'] ?? 0).' updated, '
                                .(($c['show_not_found'] ?? 0) + ($c['id_format_mismatch'] ?? 0) + ($c['other_show'] ?? 0)).' not applied';
                        })()
                        : null,
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

    /**
     * Who the token belongs to (GET /users/me), then one small read from each
     * endpoint the sync uses. A missing permission comes back from Whatnot as
     * 403 type missing-scope with required_scopes, and is named here.
     *
     * @return array{ok:bool, message:string, checks:array<string,string>}
     */
    public function probe(WhatnotApiConnection $connection): array
    {
        $client = app(WhatnotSellerApiClient::class);
        $me = $client->call($connection, 'me');
        if (! $me->successful()) {
            return ['ok' => false, 'message' => 'Whatnot returned HTTP '.$me->status().($me->status() === 401 ? ' (token not accepted)' : ''), 'checks' => ['token' => 'HTTP '.$me->status()]];
        }
        $seller = $me->json('username') ?? $me->json('name') ?? 'unknown seller';
        $checks = ['token' => 'ok ('.$seller.')'];
        $missing = [];
        foreach ([
            'orders' => ['orders', ['limit' => 1], 'read:orders'],
            'shows' => ['shows', ['limit' => 1, 'status' => 'ended'], 'read:shows'],
            'reports' => ['reports', ['limit' => 1], 'read:reports'],
        ] as $name => [$endpoint, $query, $scope]) {
            $response = $client->call($connection, $endpoint, query: $query);
            if ($response->successful()) {
                $checks[$name] = 'ok';
                if ($name === 'orders') {
                    $first = $response->json('data.0');
                    $checks['buyer_names'] = ! is_array($first) ? 'ok (no orders to check yet)'
                        : (($first['buyer'] ?? null) === null ? 'hidden (add read:customers)' : 'ok');
                }
                continue;
            }
            $required = (array) ($response->json('required_scopes') ?? []);
            $checks[$name] = $response->status() === 403 ? 'needs '.($required ? implode(', ', $required) : $scope) : 'HTTP '.$response->status();
            if ($response->status() === 403) $missing = array_merge($missing, $required ?: [$scope]);
        }
        $failed = array_filter($checks, fn ($v) => $v !== 'ok' && ! str_starts_with($v, 'ok (') && ! str_starts_with($v, 'hidden'));
        $message = $failed === []
            ? 'Connected as '.$seller.'. Orders, shows and reports readable. The financial report also needs write:reports (checked on the first sync).'
            : 'Connected as '.$seller.', but: '.implode('; ', array_map(fn ($k, $v) => "$k $v", array_keys($failed), $failed)).'.';

        return ['ok' => $failed === [], 'message' => $message, 'checks' => $checks];
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
            $result = $this->probe($connection);
            $status = $result['ok'] ? 'connected' : 'error';
            $connection->forceFill([
                'status' => $status,
                'last_checked_at' => now(),
                'last_success_at' => $result['ok'] ? now() : null,
                'last_error' => $result['ok'] ? null : $result['message'],
            ])->save();

            return ['ok' => $result['ok'], 'status' => $status, 'message' => $result['message'], 'checks' => $result['checks']];
        } catch (Throwable $e) {
            $connection->forceFill([
                'status' => 'error',
                'last_checked_at' => now(),
                'last_success_at' => null,
                'last_error' => 'Connection failed: '.$e::class,
            ])->save();
            return ['ok' => false, 'status' => 'error', 'message' => 'Connection failed'];
        }
    }
}
