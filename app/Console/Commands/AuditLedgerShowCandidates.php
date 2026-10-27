<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AuditLedgerShowCandidates extends Command
{
    protected $signature = 'ledger:audit-show-candidates {--limit=1000 : Maximum distinct ledger order IDs to inspect} {--examples=15 : Maximum examples to print}';
    protected $description = 'Read-only evidence report for historical Whatnot ledger to show recovery';

    public function handle(): int
    {
        $limit = min(100000, max(1, (int) $this->option('limit')));
        $examples = min(100, max(0, (int) $this->option('examples')));
        $stats = ['unique_channel_and_date' => 0, 'multiple_channel_and_date' => 0, 'no_channel_and_date' => 0, 'missing_ledger_timestamp' => 0, 'conflicting_ledger_channels' => 0];
        $samples = [];
        $orders = DB::table('whatnot_ledger_entries')
            ->whereNotNull('whatnot_order_id')->where('whatnot_order_id', '<>', '')
            ->where('whatnot_order_id', '<>', '-')
            ->select('whatnot_order_id')
            ->distinct()->orderBy('whatnot_order_id')->limit($limit)->pluck('whatnot_order_id');
        foreach ($orders as $orderId) {
            $ledger = DB::table('whatnot_ledger_entries')->where('whatnot_order_id', $orderId)
                ->get(['whatnot_channel_id', 'created_date']);
            $channels = $ledger->pluck('whatnot_channel_id')->filter(fn ($v) => $v !== null)->unique()->values();
            if ($channels->count() !== 1 || $ledger->contains(fn ($r) => $r->whatnot_channel_id === null)) {
                $key = 'conflicting_ledger_channels';
                $candidates = collect();
            } elseif ($ledger->contains(fn ($r) => empty($r->created_date))) {
                $key = 'missing_ledger_timestamp';
                $candidates = collect();
            } else {
                // Date-only candidates are intentionally NOT approved for automatic
                // attribution: broadcast end times and source timezone are unverified.
                $dates = $ledger->pluck('created_date')->map(fn ($v) => substr((string) $v, 0, 10))->unique();
                $candidates = DB::table('shows')->where('whatnot_channel_id', $channels->first())
                    ->whereIn('show_date', $dates->all())->pluck('id');
                $key = $candidates->count() === 1 ? 'unique_channel_and_date'
                    : ($candidates->isEmpty() ? 'no_channel_and_date' : 'multiple_channel_and_date');
            }
            $stats[$key]++;
            if (count($samples) < $examples) {
                $samples[] = [$orderId, $key, $channels->implode(','), $candidates->take(8)->implode(','), $candidates->count()];
            }
        }
        $this->table(['Classification', 'Orders'], collect($stats)->map(fn ($n, $k) => [$k, $n])->values()->all());
        $this->table(['Order ID', 'Classification', 'Channel', 'Candidate show IDs (first 8)', 'Total candidates'], $samples);
        $this->warn('READ ONLY. Date-only candidates are not verified matches. No ledger, show, or payroll records were changed.');
        return self::SUCCESS;
    }
}
