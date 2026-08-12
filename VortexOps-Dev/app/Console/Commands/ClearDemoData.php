<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ClearDemoData extends Command
{
    protected $signature = 'demo:clear {--force : Skip the confirmation prompt}';

    protected $description = 'Wipe all operational demo data. Keeps users, streamers, vendors, channels, and settings.';

    private array $tables = [
        // Deepest dependents first so FK checks (if ever re-enabled mid-run) don't bite us
        'deduction_request_lines',
        'deduction_requests',
        'show_streamers',
        'streamer_log_entries',
        'whatnot_show_orders',
        'payouts',
        'weekly_payout_batches',
        'show_ingestion_logs',
        'shows',
        // Inventory / receiving
        'inventory_movements',
        'inventory_stock',
        'inventory_lots',
        'inventory_cases',
        'pallet_lines',
        'pallets',
        'receiving_sessions',
        'products',          // renamed from inventory_items
        'inventory_locations',
        // Misc operational
        'ledger_entries',
        'time_entries',
        'streamer_loans',
        'ai_logs',
        'ai_tasks',
    ];

    public function handle(): int
    {
        if (! $this->option('force')) {
            $this->warn('This will permanently delete ALL shows, inventory, pallets, payouts, and related records.');
            $this->line('Users, streamers, vendors, Whatnot channels, and settings are NOT touched.');
            $this->newLine();

            if (! $this->confirm('Continue?')) {
                $this->line('Aborted.');
                return self::SUCCESS;
            }
        }

        $this->info('Clearing demo data…');

        // SQLite (used for tests and sometimes local dev) doesn't understand
        // MySQL's SET FOREIGN_KEY_CHECKS syntax — it has its own PRAGMA for this.
        $isSqlite = DB::connection()->getDriverName() === 'sqlite';
        DB::statement($isSqlite ? 'PRAGMA foreign_keys = OFF' : 'SET FOREIGN_KEY_CHECKS=0');

        $cleared = 0;
        foreach ($this->tables as $table) {
            if (Schema::hasTable($table)) {
                $rows = DB::table($table)->count();
                DB::table($table)->delete();
                $this->line("  <info>✓</info> {$table} ({$rows} rows)");
                $cleared++;
            }
        }

        DB::statement($isSqlite ? 'PRAGMA foreign_keys = ON' : 'SET FOREIGN_KEY_CHECKS=1');

        $this->newLine();
        $this->info("Done. {$cleared} tables cleared.");

        return self::SUCCESS;
    }
}
