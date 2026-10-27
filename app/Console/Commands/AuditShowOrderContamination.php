<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AuditShowOrderContamination extends Command
{
    protected $signature = 'whatnot:audit-show-orders {--examples=10 : Number of affected order IDs to display}';
    protected $description = 'Read-only audit of Whatnot order IDs linked to multiple shows';

    public function handle(): int
    {
        $summary = DB::table('whatnot_show_orders')
            ->selectRaw('COUNT(*) AS total_rows, COUNT(DISTINCT whatnot_order_id) AS distinct_order_ids, SUM(CASE WHEN ordered_at_whatnot IS NULL THEN 1 ELSE 0 END) AS missing_timestamps')
            ->first();
        $duplicates = DB::query()->fromSub(
            DB::table('whatnot_show_orders')
                ->whereNotNull('whatnot_order_id')->where('whatnot_order_id', '<>', '')
                ->select('whatnot_order_id')
                ->selectRaw('COUNT(DISTINCT show_id) AS show_count')
                ->groupBy('whatnot_order_id')
                ->havingRaw('COUNT(DISTINCT show_id) > 1'),
            'dupes'
        );
        $counts = (clone $duplicates)->selectRaw('COUNT(*) AS affected_ids, COALESCE(SUM(show_count),0) AS affected_links, COALESCE(MAX(show_count),0) AS max_shows')->first();
        $this->table(['Metric', 'Count'], [
            ['Order rows', $summary->total_rows],
            ['Distinct order IDs', $summary->distinct_order_ids],
            ['Missing order timestamps', $summary->missing_timestamps],
            ['Order IDs on multiple shows', $counts->affected_ids],
            ['Affected show links', $counts->affected_links],
            ['Maximum shows per order', $counts->max_shows],
        ]);
        $examples = (clone $duplicates)->orderByDesc('show_count')->limit(max(0, min(100, (int) $this->option('examples'))))->get();
        $this->table(['Order ID', 'Distinct shows'], $examples->map(fn ($r) => [$r->whatnot_order_id, $r->show_count])->all());
        $this->warn('Read-only: no records were modified. Do not delete or reassign ambiguous historical orders without authoritative evidence.');
        return self::SUCCESS;
    }
}
