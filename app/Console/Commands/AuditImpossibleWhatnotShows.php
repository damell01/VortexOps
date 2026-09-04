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
        {--repair : Apply safe repairs to impossible future-show activity}
        {--future-days=0 : Allowed number of days in the future before a show is considered impossible}';

    protected $description = 'Audit future Whatnot shows, distinguish scheduled shows from corrupt activity, and safely repair unambiguous cases.';

    public function handle(): int
    {
        $today = now()->startOfDay();
        $cutoff = $today->copy()->addDays(max(0, (int) $this->option('future-days')));
        $repair = (bool) $this->option('repair');

        $future = Show::query()
            ->whereDate('show_date', '>', $cutoff->toDateString())
            ->orderBy('show_date')
            ->get();

        if ($future->isEmpty()) {
            $this->info('No future-dated shows found.');
            return self::SUCCESS;
        }

        $this->warn("Found {$future->count()} future-dated show record(s) after {$cutoff->toDateString()}.");

        $rows = [];
        $repaired = 0;
        $cleaned = 0;
        $flagged = 0;

        foreach ($future as $show) {
            $orderCount = $this->childCount('whatnot_show_orders', $show->id);
            $shipmentCount = $this->childCount('shipments', $show->id);
            $signals = $this->historicalSignals($show->id, $today);
            $candidate = $this->unambiguousCandidate($signals);

            $units = (int) ($show->units_sold ?? 0);
            $gross = (float) ($show->gross_revenue ?? 0);
            $estimatedNet = (float) ($show->whatnot_net ?? 0);
            $completed = (float) ($show->completed_earnings ?? 0);

            $activity = array_values(array_filter([
                $units > 0 ? "units={$units}" : null,
                $gross != 0.0 ? 'gross' : null,
                $estimatedNet != 0.0 ? 'estimated-net' : null,
                $completed != 0.0 ? 'completed' : null,
                $orderCount > 0 ? "orders={$orderCount}" : null,
                $shipmentCount > 0 ? "shipments={$shipmentCount}" : null,
            ]));

            $hasActivity = $activity !== [];
            $action = 'flagged';

            if (! $hasActivity && empty($signals)) {
                $action = 'future/no activity';
            } elseif ($repair && $candidate !== null) {
                $old = $show->show_date?->format('Y-m-d');
                DB::transaction(function () use ($show, $candidate): void {
                    $show->forceFill(['show_date' => $candidate])->save();
                    if (Schema::hasTable('whatnot_show_orders') && Schema::hasColumn('whatnot_show_orders', 'show_date')) {
                        DB::table('whatnot_show_orders')->where('show_id', $show->id)->update(['show_date' => $candidate]);
                    }
                });
                $action = "date {$old} -> {$candidate}";
                $repaired++;
            } elseif ($repair && $this->isPureOrderPollution($units, $gross, $estimatedNet, $completed, $orderCount, $shipmentCount)) {
                // This is the exact corrupt shape we observed in production:
                // a scheduled future show with zero Whatnot analytics/earnings and
                // zero shipments, but a batch of imported order rows. There is no
                // valid business state in which completed orders exist before the
                // show while every show-level sales metric is zero. Remove only the
                // impossible child rows; preserve the scheduled show itself.
                DB::transaction(function () use ($show): void {
                    DB::table('whatnot_show_orders')->where('show_id', $show->id)->delete();
                    $show->forceFill(['last_synced_at' => null])->save();
                });
                $action = "removed {$orderCount} impossible order row(s)";
                $cleaned++;
            } else {
                $flagged++;
            }

            $rows[] = [
                $show->id,
                $show->show_date?->format('Y-m-d'),
                mb_strimwidth((string) $show->title, 0, 34, '…'),
                $units,
                $orderCount,
                $shipmentCount,
                number_format($gross, 2),
                $activity ? implode(', ', $activity) : '—',
                $candidate ?? '—',
                $action,
            ];
        }

        $this->table(
            ['ID', 'Stored date', 'Show', 'Units', 'Imported orders', 'Shipments', 'Gross', 'Activity', 'Candidate', 'Action'],
            $rows
        );

        if (! $repair) {
            $this->newLine();
            $this->comment('Dry run only. Re-run with --repair to fix unambiguous dates and remove pure future-show order pollution.');
        }

        $this->info("Date repaired: {$repaired}; order pollution cleaned: {$cleaned}; needs review: {$flagged}.");
        return self::SUCCESS;
    }

    private function childCount(string $table, int $showId): int
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'show_id')) {
            return 0;
        }

        return (int) DB::table($table)->where('show_id', $showId)->count();
    }

    private function isPureOrderPollution(
        int $units,
        float $gross,
        float $estimatedNet,
        float $completed,
        int $orderCount,
        int $shipmentCount,
    ): bool {
        return $orderCount > 0
            && $shipmentCount === 0
            && $units === 0
            && abs($gross) < 0.01
            && abs($estimatedNet) < 0.01
            && abs($completed) < 0.01;
    }

    /**
     * Return trustworthy past dates grouped by source. Import timestamps are
     * deliberately excluded because the time data arrived is not the show date.
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
