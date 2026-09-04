<?php

namespace App\Console\Commands;

use App\Models\Show;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuditImpossibleWhatnotShows extends Command
{
    protected $signature = 'whatnot:audit-impossible-shows
        {--repair : Repair only records where a trustworthy historical date can be inferred}
        {--future-days=0 : Allowed number of days in the future before a show is considered impossible}';

    protected $description = 'Audit impossible Whatnot show dates and safely repair dates from attached historical order data when unambiguous.';

    public function handle(): int
    {
        $today = now()->startOfDay();
        $cutoff = $today->copy()->addDays(max(0, (int) $this->option('future-days')));
        $repair = (bool) $this->option('repair');

        $query = Show::query()->whereDate('show_date', '>', $cutoff->toDateString());
        $future = $query->orderBy('show_date')->get();

        if ($future->isEmpty()) {
            $this->info('No future-dated shows found.');
            return self::SUCCESS;
        }

        $this->warn("Found {$future->count()} future-dated show record(s) after {$cutoff->toDateString()}.");

        $rows = [];
        $repaired = 0;
        $flagged = 0;

        foreach ($future as $show) {
            $signals = $this->historicalSignals($show->id, $today);
            $hasActivity = $this->hasActivity($show);
            $candidate = $this->unambiguousCandidate($signals);
            $action = 'flagged';

            // A future scheduled show with no completed activity can be legitimate.
            // A future show with sales/orders/earnings/shipments is impossible and must
            // never be silently accepted as historical data.
            if (! $hasActivity && empty($signals)) {
                $action = 'future/no activity';
            } elseif ($repair && $candidate !== null) {
                $old = $show->show_date?->format('Y-m-d');
                $show->forceFill(['show_date' => $candidate])->save();
                $action = "repaired {$old} -> {$candidate}";
                $repaired++;
            } else {
                $flagged++;
            }

            $rows[] = [
                $show->id,
                $show->show_date?->format('Y-m-d'),
                mb_strimwidth((string) $show->title, 0, 42, '…'),
                (int) ($show->units_sold ?? 0),
                number_format((float) ($show->gross_revenue ?? 0), 2),
                implode(', ', array_keys($signals)) ?: '—',
                $candidate ?? '—',
                $action,
            ];
        }

        $this->table(
            ['ID', 'Stored date', 'Show', 'Orders', 'Gross', 'Date sources', 'Candidate', 'Action'],
            $rows
        );

        if (! $repair) {
            $this->newLine();
            $this->comment('Dry run only. Re-run with --repair to apply only unambiguous historical dates.');
        }

        $this->info("Repaired: {$repaired}; needs review: {$flagged}.");
        return self::SUCCESS;
    }

    private function hasActivity(Show $show): bool
    {
        if ((int) ($show->units_sold ?? 0) > 0
            || (float) ($show->gross_revenue ?? 0) > 0
            || (float) ($show->whatnot_net ?? 0) > 0
            || (float) ($show->completed_earnings ?? 0) > 0) {
            return true;
        }

        foreach (['whatnot_show_orders', 'shipments'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'show_id')
                && DB::table($table)->where('show_id', $show->id)->exists()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Return trustworthy past dates grouped by their source. We intentionally
     * avoid guessing from created_at/updated_at because import time is not show time.
     */
    private function historicalSignals(int $showId, Carbon $today): array
    {
        $signals = [];

        if (Schema::hasTable('whatnot_show_orders')
            && Schema::hasColumn('whatnot_show_orders', 'show_id')
            && Schema::hasColumn('whatnot_show_orders', 'show_date')) {
            $dates = DB::table('whatnot_show_orders')
                ->where('show_id', $showId)
                ->whereNotNull('show_date')
                ->whereDate('show_date', '<=', $today->toDateString())
                ->selectRaw('DATE(show_date) as d, COUNT(*) as c')
                ->groupByRaw('DATE(show_date)')
                ->orderByDesc('c')
                ->get();

            if ($dates->count() === 1) {
                $signals['orders'] = (string) $dates->first()->d;
            }
        }

        // Some shipment schemas carry an explicit show_date. Use it only when
        // every shipment attached to the show agrees on the same historical date.
        if (Schema::hasTable('shipments')
            && Schema::hasColumn('shipments', 'show_id')
            && Schema::hasColumn('shipments', 'show_date')) {
            $dates = DB::table('shipments')
                ->where('show_id', $showId)
                ->whereNotNull('show_date')
                ->whereDate('show_date', '<=', $today->toDateString())
                ->selectRaw('DATE(show_date) as d, COUNT(*) as c')
                ->groupByRaw('DATE(show_date)')
                ->get();

            if ($dates->count() === 1) {
                $signals['shipments'] = (string) $dates->first()->d;
            }
        }

        return $signals;
    }

    private function unambiguousCandidate(array $signals): ?string
    {
        if ($signals === []) {
            return null;
        }

        $dates = array_values(array_unique($signals));
        return count($dates) === 1 ? $dates[0] : null;
    }
}
