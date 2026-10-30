<?php

namespace App\Services;

use App\Models\WhatnotApiConnection;
use App\Models\WhatnotChannel;
use Illuminate\Support\Facades\Http;
use Throwable;

class WhatnotApiHealth
{
    public function overview(): array
    {
        return WhatnotChannel::query()->orderBy('id')->get()->map(function (WhatnotChannel $channel): array {
            $connection = WhatnotApiConnection::query()->where('whatnot_channel_id', $channel->id)->first();
            return [
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

    public function check(WhatnotApiConnection $connection): array
    {
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
