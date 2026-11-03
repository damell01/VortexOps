<?php

namespace App\Console\Commands;

use App\Models\WhatnotApiConnection;
use App\Services\WhatnotApiVerifier;
use Illuminate\Console\Command;

/**
 * Read-only: compare the Seller API with VortexOps for recent shows (gross,
 * orders, matching order IDs) and tips, before switching writes on.
 */
final class WhatnotApiVerify extends Command
{
    protected $signature = 'whatnot:api-verify {--channel= : Whatnot channel ID (required)} {--days=7} {--json}';

    protected $description = 'Compare Whatnot Seller API numbers with VortexOps (read-only).';

    public function handle(WhatnotApiVerifier $verifier): int
    {
        $connection = WhatnotApiConnection::where('whatnot_channel_id', (int) $this->option('channel'))->first();
        if (! $connection || blank($connection->access_token)) {
            $this->error('No saved API token for that channel.');
            return self::FAILURE;
        }
        $report = $verifier->verify($connection, max(1, (int) $this->option('days')));

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT));
            return self::SUCCESS;
        }
        $this->table(['Check', 'Result'], collect($report['connection'] ?? [])->map(fn ($v, $k) => [$k, $v])->values()->all());
        if ($report['shows'] ?? []) {
            $this->table(['Show', 'Date', 'API gross', 'VortexOps gross', 'API orders', 'VortexOps orders', 'Matching IDs', 'Status'],
                array_map(fn ($r) => array_values($r), $report['shows']));
        }
        if (isset($report['tips'])) $this->line('Tips: '.json_encode($report['tips']));
        foreach ($report['problems'] as $problem) $this->warn($problem);
        $this->info($report['verdict']);

        return self::SUCCESS;
    }
}
