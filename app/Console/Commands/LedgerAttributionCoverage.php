<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class LedgerAttributionCoverage extends Command
{
    protected $signature = 'ledger:coverage {--json : Output machine-readable JSON}';
    protected $description = 'Audit ledger attribution coverage and outstanding financial exceptions without changing records';

    public function handle(): int
    {
        $total = DB::table('whatnot_ledger_entries')->count();
        $base = DB::table('whatnot_ledger_entries as e')
            ->leftJoin('ledger_attributions as a', 'a.whatnot_ledger_entry_id', '=', 'e.id');

        $stats = [
            'total' => $total,
            'approved_show' => (clone $base)->where('a.status', 'approved')->where('a.target', 'show')->count(),
            'approved_streamer' => (clone $base)->where('a.status', 'approved')->where('a.target', 'streamer')->count(),
            'approved_house' => (clone $base)->where('a.status', 'approved')->where('a.target', 'house')->count(),
            'pending_review' => (clone $base)->where('a.status', 'pending')->count(),
            'rejected' => (clone $base)->where('a.status', 'rejected')->count(),
            'unmatched' => (clone $base)->whereNull('a.id')->count(),
            'unassigned' => (clone $base)->where('a.status', 'approved')->where('a.target', 'unassigned')->count(),
        ];
        $stats['approved_uncategorized'] = (clone $base)->where('a.status', 'approved')->where('a.category', 'other')->count();
        $stats['approved_without_business'] = (clone $base)->where('a.status', 'approved')->whereNull('a.business_id')->count();
        $stats['approved_show_without_streamer'] = (clone $base)->where('a.status', 'approved')->where('a.target', 'show')->whereNull('a.streamer_id')->count();
        $stats['approved_total'] = $stats['approved_show'] + $stats['approved_streamer'] + $stats['approved_house'];
        $stats['approved_percent'] = $total ? round(100 * $stats['approved_total'] / $total, 2) : 0;

        if ($this->option('json')) {
            $this->line(json_encode($stats, JSON_THROW_ON_ERROR));
        } else {
            $this->table(['Metric', 'Count'], collect($stats)->map(fn ($value, $key) => [$key, $value])->values()->all());
        }

        return self::SUCCESS;
    }
}
