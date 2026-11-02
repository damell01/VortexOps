<?php

namespace App\Console\Commands;

use App\Models\WhatnotApiConnection;
use App\Services\WhatnotApiHealth;
use Illuminate\Console\Command;

class WhatnotApiDiagnose extends Command
{
    protected $signature = 'whatnot:api-diagnose {--check : Make a read-only authenticated connection check for configured channels} {--json : Print sanitized JSON}';
    protected $description = 'Show per-channel API readiness, capture freshness and webhook inbox without exposing credentials';

    public function handle(WhatnotApiHealth $health): int
    {
        $checks = [];
        if ($this->option('check')) {
            if (! app()->environment('production')
                || ! config('services.whatnot_seller.live_requests_enabled', false)) {
                $this->error('Live checks are disabled. Run only on production with the explicit live requests flag.');
                return self::FAILURE;
            }
            foreach (WhatnotApiConnection::query()->whereNotNull('access_token')->get() as $connection) {
                $result = $health->check($connection);
                $checks[(string) $connection->whatnot_channel_id] = [
                    'ok' => (bool) $result['ok'],
                    'status' => $result['status'],
                    'message' => $result['message'] ?? null,
                ];
            }
        }
        $rows = collect($health->overview())->map(fn ($channel) => [
            'channel_id' => $channel['channel_id'],
            'channel' => $channel['name'],
            'configured' => $channel['configured'],
            'enabled' => $channel['enabled'],
            'sync_enabled' => $channel['sync_enabled'],
            'status' => $channel['status'],
            'last_success_at' => $channel['last_success_at'],
            'data_health' => $channel['data_health'],
            'snapshots' => $channel['snapshots'],
            'webhook_inbox' => $channel['webhook_inbox'],
            'check' => $checks[(string) $channel['channel_id']] ?? null,
        ])->all();
        if ($this->option('json')) {
            $this->line(json_encode([
                'live_requests_allowed' => app()->environment('production')
                    && (bool) config('services.whatnot_seller.live_requests_enabled', false),
                'contract_verified' => (bool) config('services.whatnot_seller.contract_verified', false),
                'pagination_verified' => (bool) config('services.whatnot_seller.pagination_verified', false),
                'scheduler_enabled' => (bool) config('services.whatnot_seller.sync_scheduler_enabled', false),
                'channels' => $rows,
            ], JSON_THROW_ON_ERROR));
        } else {
            $this->table(['Channel', 'Configured', 'Status', 'Sync', 'Shows', 'Orders', 'Reports'],
                array_map(fn ($row) => [
                    $row['channel'], $row['configured'] ? 'yes' : 'no',
                    $row['status'], $row['sync_enabled'] ? 'on' : 'off',
                    $row['data_health']['shows'], $row['data_health']['orders'], $row['data_health']['reports'],
                ], $rows));
            foreach ($checks as $channel => $result) {
                $this->line("Channel {$channel}: ".$result['status'].($result['message'] ? ' ('.$result['message'].')' : ''));
            }
        }
        return count(array_filter($checks, fn ($result) => ! $result['ok'])) ? self::FAILURE : self::SUCCESS;
    }
}
