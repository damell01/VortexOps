<?php

namespace App\Console\Commands;

use App\Models\WhatnotApiConnection;
use App\Services\WhatnotApiOrderApplier;
use App\Services\WhatnotApiSync;
use Illuminate\Console\Command;

/**
 * Run one Seller API sync for a channel by hand: orders, line items, show
 * analytics and the financial report. A preview by default (live reads,
 * no writes); --apply writes, when writes are allowed.
 */
final class WhatnotApiSyncCommand extends Command
{
    protected $signature = 'whatnot:api-sync
        {--channel= : Whatnot channel ID (required)}
        {--days=3 : How many days back to look}
        {--apply : Write changes instead of previewing}
        {--json : Print the summary as JSON}';

    protected $description = 'Preview or run a full Whatnot Seller API sync for one channel.';

    public function handle(WhatnotApiSync $sync): int
    {
        $connection = WhatnotApiConnection::where('whatnot_channel_id', (int) $this->option('channel'))->first();
        if (! $connection || blank($connection->access_token)) {
            $this->error('No saved API token for that channel. Save one on the Whatnot API page first.');
            return self::FAILURE;
        }
        $apply = (bool) $this->option('apply');
        if ($apply && ! WhatnotApiOrderApplier::writesAllowed()) {
            $this->error('Writing is off. It needs production, WHATNOT_SELLER_LIVE_REQUESTS_ENABLED, WHATNOT_SELLER_CONTRACT_VERIFIED and WHATNOT_SELLER_APPLY_ENABLED.');
            return self::FAILURE;
        }

        $result = $sync->run($connection, max(1, (int) $this->option('days')), dryRun: ! $apply,
            log: $this->option('json') ? null : fn (string $line) => $this->line($line));

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT));
        } else {
            $this->info($apply ? 'Sync applied.' : 'Preview finished — nothing written. Add --apply to write.');
            foreach ($result['errors'] as $error) $this->warn($error);
        }

        return $result['errors'] ? self::FAILURE : self::SUCCESS;
    }
}
