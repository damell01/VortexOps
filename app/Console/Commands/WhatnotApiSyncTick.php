<?php

namespace App\Console\Commands;

use App\Models\WhatnotApiConnection;
use App\Services\WhatnotApiReadOnlySync;
use App\Services\WhatnotSellerApiClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Dormant until explicitly activated on production. Does not use browser profiles.
 * Captures read-only API snapshots; never writes operational orders, ledger or payroll.
 */
final class WhatnotApiSyncTick extends Command
{
    protected $signature = 'whatnot:api-sync-tick {--channel= : Optional channel ID}';
    protected $description = 'Safely capture enabled Whatnot API resources when production API mode is active.';

    public function handle(WhatnotApiReadOnlySync $sync, WhatnotSellerApiClient $client): int
    {
        if (! app()->environment('production')
            || ! config('services.whatnot_seller.live_requests_enabled', false)
            || ! config('services.whatnot_seller.sync_scheduler_enabled', false)
            || ! config('services.whatnot_seller.contract_verified', false)) {
            $this->line('Whatnot API scheduled capture is disabled.');
            return self::SUCCESS;
        }

        $query = WhatnotApiConnection::query()->where('enabled', true)->where('sync_enabled', true);
        if ($this->option('channel') !== null) {
            $query->where('whatnot_channel_id', (int) $this->option('channel'));
        }
        $failures = 0;
        foreach ($query->get() as $connection) {
            foreach (['shows', 'orders'] as $resource) {
                $lock = Cache::lock('whatnot-api-capture:'.$connection->whatnot_channel_id.':'.$resource, 240);
                if (! $lock->get()) {
                    continue;
                }
                try {
                    $result = $sync->capture($connection, $resource, $client);
                    if (! $result['ok']) {
                        $failures++;
                        $this->warn('Capture failed for channel '.$connection->whatnot_channel_id.' '.$resource.' (HTTP '.($result['http_status'] ?? 'unknown').')');
                    }
                } catch (Throwable $e) {
                    $failures++;
                    report($e);
                    $this->warn('Capture error for channel '.$connection->whatnot_channel_id.' '.$resource.' ('.get_class($e).')');
                } finally {
                    $lock->release();
                }
            }
        }
        return $failures ? self::FAILURE : self::SUCCESS;
    }
}
