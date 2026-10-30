<?php

namespace App\Console\Commands;

use App\Models\WhatnotApiConnection;
use App\Services\WhatnotApiReadOnlySync;
use App\Services\WhatnotSellerApiClient;
use Illuminate\Console\Command;

final class WhatnotApiCapture extends Command
{
    protected $signature = 'whatnot:api-capture {channel : Channel ID} {resource : shows, orders or reports} {--confirm-read-only : Explicitly permit API request and snapshot storage} {--inspect : Show safe schema metadata from stored snapshots without API requests} {--preview-show-snapshot= : Offline dry-run match of a stored shows snapshot by ID} {--preview-order-snapshot= : Offline order ID coverage preview}';
    protected $description = 'Manually capture one read-only API response; does not update shows, orders, ledger or payroll.';

    public function handle(WhatnotApiReadOnlySync $sync, WhatnotSellerApiClient $client): int
    {
        if ($this->option('preview-order-snapshot') !== null) {
            if ((string) $this->argument('resource') !== 'orders' || (int) $this->option('preview-order-snapshot') < 1) {
                $this->error('Specify resource orders and a positive snapshot ID.');
                return self::FAILURE;
            }
            $this->line(json_encode($sync->previewOrders((int) $this->argument('channel'), (int) $this->option('preview-order-snapshot')), JSON_PRETTY_PRINT));
            return self::SUCCESS;
        }
        if ($this->option('preview-show-snapshot') !== null) {
            if ((string) $this->argument('resource') !== 'shows' || (int) $this->option('preview-show-snapshot') < 1) {
                $this->error('Specify resource shows and a positive snapshot ID.');
                return self::FAILURE;
            }
            $this->line(json_encode($sync->previewShows((int) $this->argument('channel'), (int) $this->option('preview-show-snapshot')), JSON_PRETTY_PRINT));
            return self::SUCCESS;
        }
        if ($this->option('inspect')) {
            $resource = (string) $this->argument('resource');
            if (! in_array($resource, ['shows', 'orders', 'reports'], true) || (int) $this->argument('channel') < 1) {
                $this->error('Use an existing positive channel ID and resource shows or orders.');
                return self::FAILURE;
            }
            $this->line(json_encode($sync->inspect((int) $this->argument('channel'), $resource), JSON_PRETTY_PRINT));
            return self::SUCCESS;
        }
        if (! $this->option('confirm-read-only')) {
            $this->error('Add --confirm-read-only to explicitly request a snapshot.');
            return self::FAILURE;
        }
        $connection = WhatnotApiConnection::where('whatnot_channel_id', (int) $this->argument('channel'))->first();
        if (! $connection || blank($connection->access_token)) {
            $this->error('No credentials for this channel.');
            return self::FAILURE;
        }
        $result = $sync->capture($connection, (string) $this->argument('resource'), $client);
        $this->line(json_encode($result));
        return $result['ok'] ? self::SUCCESS : self::FAILURE;
    }
}
