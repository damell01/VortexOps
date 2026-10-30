<?php

namespace App\Console\Commands;

use App\Models\WhatnotApiConnection;
use App\Services\WhatnotApiReadOnlySync;
use App\Services\WhatnotSellerApiClient;
use Illuminate\Console\Command;

final class WhatnotApiCapture extends Command
{
    protected $signature = 'whatnot:api-capture {channel : Channel ID} {resource : shows or orders} {--confirm-read-only : Explicitly permit API request and snapshot storage}';
    protected $description = 'Manually capture one read-only API response; does not update shows, orders, ledger or payroll.';

    public function handle(WhatnotApiReadOnlySync $sync, WhatnotSellerApiClient $client): int
    {
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
