<?php

namespace App\Console\Commands;

use App\Models\Show;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RepairDuplicateWhatnotAnalytics extends Command
{
    protected $signature = 'whatnot:repair-duplicate-analytics
        {--days=180 : How far back to inspect completed shows}
        {--min=3 : Minimum identical signatures before a group is quarantined}
        {--apply : Actually clear the suspicious analytics so they can be re-scraped}';

    protected $description = 'Quarantine suspicious cloned Whatnot analytics, including partial Gross + Est Net clones, and make those shows eligible for a clean backfill.';

    private const ANALYTICS_FIELDS = [
        'gross_revenue',
        'whatnot_net',
        'completed_earnings',
        'avg_order_value',
        'giveaway_spend',
        'units_sold',
        'giveaways_count',
        'buyers_count',
        'first_time_buyers',
        'returning_buyers',
        'shares_count',
        'show_duration',
        'max_concurrent_viewers',
        'total_views',
        'avg_order_rating',
    ];

    private const CLEAR_VALUES = [
        'gross_revenue' => 0,
        'whatnot_net' => 0,
        'units_sold' => 0,
        'completed_earnings' => null,
        'avg_order_value' => null,
        'giveaway_spend' => null,
        'giveaways_count' => null,
        'buyers_count' => null,
        'first_time_buyers' => null,
        'returning_buyers' => null,
        'shares_count' => null,
        'show_duration' => null,
        'max_concurrent_viewers' => null,
        'total_views' => null,
        'avg_order_rating' => null,
    ];

    public function handle(): int
    {
        $days = max(1, min(3650, (int) $this->option('days')));
        $min = max(2, min(50, (int) $this->option('min')));

        $shows = Show::query()
            ->whereDate('show_date', '<=', today())
            ->whereDate('show_date', '>=', today()->subDays($days))
            ->whereNotIn('status', ['cancelled'])
            ->whereNotNull('gross_revenue')
            ->where('gross_revenue', '>', 0)
            ->whereNotNull('whatnot_show_id')
            ->get(array_merge([
                'id', 'show_date', 'title', 'whatnot_show_id', 'raw_import_payload',
                'last_analytics_synced_at',
            ], self::ANALYTICS_FIELDS));

        $fullGroups = $shows
            ->groupBy(fn (Show $show) => $this->signature($show))
            ->filter(fn (Collection $group) => $group->count() >= $min)
            ->filter(fn (Collection $group) => $group->pluck('whatnot_show_id')->filter()->unique()->count() > 1)
            ->sortByDesc->count();

        $partialGroups = $shows
            ->groupBy(fn (Show $show) => $this->financialPair($show))
            ->filter(fn (Collection $group) => $group->count() >= $min)
            ->filter(fn (Collection $group) => $group->pluck('whatnot_show_id')->filter()->unique()->count() > 1)
            ->filter(fn (Collection $group) => $this->operationalMetricsVary($group))
            ->sortByDesc->count();

        if ($fullGroups->isEmpty() && $partialGroups->isEmpty()) {
            $this->info('No suspicious duplicate or partial-clone analytics groups were found.');
            return self::SUCCESS;
        }

        if ($fullGroups->isNotEmpty()) {
            $this->warn('Suspicious full cloned analytics groups found:');
            $this->table(
                ['Shows', 'Gross', 'Est Net', 'Completed', 'AOV', 'Units', 'UUIDs', 'Dates'],
                $fullGroups->map(function (Collection $group) {
                    $first = $group->first();
                    return [
                        $group->count(),
                        '$' . number_format((float) $first->gross_revenue, 2),
                        '$' . number_format((float) ($first->whatnot_net ?? 0), 2),
                        '$' . number_format((float) ($first->completed_earnings ?? 0), 2),
                        '$' . number_format((float) ($first->avg_order_value ?? 0), 2),
                        (int) ($first->units_sold ?? 0),
                        $group->pluck('whatnot_show_id')->filter()->unique()->count(),
                        $group->min('show_date') . ' → ' . $group->max('show_date'),
                    ];
                })->values()->all()
            );
        }

        if ($partialGroups->isNotEmpty()) {
            $this->newLine();
            $this->warn('Suspicious partial clones found (same Gross + Est Net, different units/buyers):');
            $this->table(
                ['Shows', 'Gross', 'Est Net', 'Unit values', 'Buyer values', 'UUIDs', 'Dates'],
                $partialGroups->map(function (Collection $group) {
                    $first = $group->first();
                    return [
                        $group->count(),
                        '$' . number_format((float) $first->gross_revenue, 2),
                        '$' . number_format((float) ($first->whatnot_net ?? 0), 2),
                        $group->pluck('units_sold')->map(fn ($v) => (int) ($v ?? 0))->unique()->count(),
                        $group->pluck('buyers_count')->map(fn ($v) => (int) ($v ?? 0))->unique()->count(),
                        $group->pluck('whatnot_show_id')->filter()->unique()->count(),
                        $group->min('show_date') . ' → ' . $group->max('show_date'),
                    ];
                })->values()->all()
            );
        }

        $reasons = [];
        foreach ($fullGroups as $group) {
            foreach ($group as $show) {
                $reasons[$show->id] = 'duplicate_full_analytics_signature_across_different_show_uuids';
            }
        }
        foreach ($partialGroups as $group) {
            foreach ($group as $show) {
                $reasons[$show->id] ??= 'duplicate_gross_and_estimated_net_with_varying_operational_metrics';
            }
        }

        $suspects = $shows->whereIn('id', array_keys($reasons))->unique('id')->values();
        $this->line('Affected shows: ' . $suspects->count());

        if (! $this->option('apply')) {
            $this->newLine();
            $this->comment('Dry run only. Re-run with --apply to quarantine these values.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($suspects, $reasons): void {
            foreach ($suspects as $show) {
                $raw = is_array($show->raw_import_payload) ? $show->raw_import_payload : [];
                $quarantine = is_array($raw['_analytics_quarantine'] ?? null)
                    ? $raw['_analytics_quarantine']
                    : [];

                $snapshot = [
                    'quarantined_at' => now()->toIso8601String(),
                    'reason' => $reasons[$show->id] ?? 'duplicate_analytics_detected',
                    'whatnot_show_id' => $show->whatnot_show_id,
                    'values' => [],
                ];

                foreach (self::ANALYTICS_FIELDS as $field) {
                    $snapshot['values'][$field] = $show->{$field};
                }

                $quarantine[] = $snapshot;
                $raw['_analytics_quarantine'] = array_slice($quarantine, -5);
                unset($raw['_analytics_metrics'], $raw['_analytics_synced_at']);

                DB::table('shows')->where('id', $show->id)->update(array_merge(
                    self::CLEAR_VALUES,
                    [
                        'raw_import_payload' => json_encode($raw, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                        'last_analytics_synced_at' => null,
                        'updated_at' => now(),
                    ],
                ));
            }
        });

        $this->newLine();
        $this->info('Quarantined analytics for ' . $suspects->count() . ' show(s).');
        $this->line('The old values were preserved under raw_import_payload._analytics_quarantine.');
        $this->line('Next: php artisan whatnot:backfill-missing-analytics --days=' . $days . ' --limit=500');

        return self::SUCCESS;
    }

    private function signature(Show $show): string
    {
        return implode('|', [
            number_format((float) $show->gross_revenue, 2, '.', ''),
            number_format((float) ($show->whatnot_net ?? 0), 2, '.', ''),
            number_format((float) ($show->completed_earnings ?? 0), 2, '.', ''),
            number_format((float) ($show->avg_order_value ?? 0), 2, '.', ''),
            (int) ($show->units_sold ?? 0),
            (int) ($show->buyers_count ?? 0),
        ]);
    }

    private function financialPair(Show $show): string
    {
        return implode('|', [
            number_format((float) $show->gross_revenue, 2, '.', ''),
            number_format((float) ($show->whatnot_net ?? 0), 2, '.', ''),
        ]);
    }

    private function operationalMetricsVary(Collection $group): bool
    {
        $units = $group->pluck('units_sold')->map(fn ($v) => (int) ($v ?? 0))->unique()->count();
        $buyers = $group->pluck('buyers_count')->map(fn ($v) => (int) ($v ?? 0))->unique()->count();
        return $units > 1 || $buyers > 1;
    }
}
