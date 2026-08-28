<?php

namespace App\Console\Commands;

use App\Jobs\ProcessWhatnotChannelsJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;

class ProcessWhatnotChannels extends Command
{
    protected $signature = 'whatnot:process-channels
                            {--type=incremental : incremental, last_30_days, or full}
                            {--ledger-days=30 : Rolling ledger window per channel}
                            {--shipment-limit=50 : Maximum unresolved shipment shows per channel}
                            {--queue : Queue the pipeline instead of running it in this terminal}';

    protected $description = 'Process every enabled Whatnot channel sequentially: shows, analytics, orders, shipments, and ledger';

    public function handle(): int
    {
        $type = (string) $this->option('type');
        if (! in_array($type, ['incremental', 'last_30_days', 'full'], true)) {
            $this->error('Invalid --type. Use incremental, last_30_days, or full.');
            return self::FAILURE;
        }

        $job = new ProcessWhatnotChannelsJob(
            type: $type,
            ledgerDays: max(1, (int) $this->option('ledger-days')),
            shipmentLimit: max(1, (int) $this->option('shipment-limit')),
        );

        if ($this->option('queue')) {
            dispatch($job);
            $this->info('Queued the sequential Whatnot channel pipeline.');
            return self::SUCCESS;
        }

        $this->info('Running the Whatnot channel pipeline now. Each channel will finish before the next starts.');
        Bus::dispatchSync($job);
        $this->info('Whatnot channel pipeline finished. Check Whatnot Syncs / ingestion logs for per-channel results.');

        return self::SUCCESS;
    }
}
