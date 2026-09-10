<?php

namespace App\Console\Commands;

use App\Models\Show;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class AuditDuplicateWhatnotAnalytics extends Command
{
    protected $signature = 'whatnot:audit-duplicate-analytics
        {--days=180 : How far back to inspect completed shows}
        {--min=3 : Minimum repeats before a value/signature is reported}
        {--gross= : Inspect one exact gross-revenue value, e.g. 1201 or 3264}';

    protected $description = 'Find suspicious repeated Whatnot analytics, including full and partial financial clones, and inspect stored UUID evidence.';

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
            ->orderByDesc('show_date')
            ->get([
                'id', 'show_date', 'title', 'whatnot_show_id', 'whatnot_channel_id',
                'gross_revenue', 'whatnot_net', 'completed_earnings', 'avg_order_value',
                'units_sold', 'buyers_count', 'last_analytics_synced_at', 'last_synced_at',
                'raw_import_payload',
            ]);

        if ($gross = $this->option('gross')) {
            $target = round((float) str_replace([',', '$'], '', (string) $gross), 2);
            $shows = $shows->filter(fn (Show $show) => round((float) $show->gross_revenue, 2) === $target)->values();
        }

        if ($shows->isEmpty()) {
            $this->info('No matching completed shows with positive gross revenue were found.');
            return self::SUCCESS;
        }

        $this->info('Whatnot analytics duplicate audit');
        $this->line('Shows inspected: ' . $shows->count());

        $grossGroups = $shows
            ->groupBy(fn (Show $show) => number_format((float) $show->gross_revenue, 2, '.', ''))
            ->filter(fn (Collection $group) => $group->count() >= $min)
            ->sortByDesc->count();

        $this->newLine();
        $this->comment("Repeated gross values (minimum {$min})");
        if ($grossGroups->isEmpty()) {
            $this->line('  none');
        } else {
            $this->table(
                ['Gross', 'Shows', 'Unique UUIDs', 'Date range'],
                $grossGroups->map(function (Collection $group, string $gross) {
                    return [
                        '$' . number_format((float) $gross, 2),
                        $group->count(),
                        $group->pluck('whatnot_show_id')->filter()->unique()->count(),
                        $this->formatDate($group->min('show_date')) . ' → ' . $this->formatDate($group->max('show_date')),
                    ];
                })->values()->all()
            );
        }

        $signatureGroups = $shows
            ->groupBy(fn (Show $show) => $this->signature($show))
            ->filter(fn (Collection $group) => $group->count() >= $min)
            ->filter(fn (Collection $group) => $group->pluck('whatnot_show_id')->filter()->unique()->count() > 1)
            ->sortByDesc->count();

        $this->newLine();
        $this->comment("Repeated full analytics signatures across different UUIDs (minimum {$min})");
        if ($signatureGroups->isEmpty()) {
            $this->line('  none');
        } else {
            $this->table(
                ['Shows', 'Gross', 'Est Net', 'Units', 'Completed', 'AOV', 'UUIDs'],
                $signatureGroups->map(function (Collection $group) {
                    $first = $group->first();
                    return [
                        $group->count(),
                        '$' . number_format((float) $first->gross_revenue, 2),
                        '$' . number_format((float) ($first->whatnot_net ?? 0), 2),
                        (int) ($first->units_sold ?? 0),
                        '$' . number_format((float) ($first->completed_earnings ?? 0), 2),
                        '$' . number_format((float) ($first->avg_order_value ?? 0), 2),
                        $group->pluck('whatnot_show_id')->filter()->unique()->count(),
                    ];
                })->values()->all()
            );
        }

        // A full signature can stop matching once another importer later updates
        // units/buyers while the stale financial cards remain cloned. Catch that
        // partial-corruption shape by looking for an exact gross + estimated-net
        // pair repeated across different UUIDs while operational metrics vary.
        $partialGroups = $shows
            ->groupBy(fn (Show $show) => $this->financialPair($show))
            ->filter(fn (Collection $group) => $group->count() >= $min)
            ->filter(fn (Collection $group) => $group->pluck('whatnot_show_id')->filter()->unique()->count() > 1)
            ->filter(fn (Collection $group) => $this->operationalMetricsVary($group))
            ->sortByDesc->count();

        $this->newLine();
        $this->comment("Suspicious partial clones: same Gross + Est Net, different operational metrics (minimum {$min})");
        if ($partialGroups->isEmpty()) {
            $this->line('  none');
        } else {
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
                        $this->formatDate($group->min('show_date')) . ' → ' . $this->formatDate($group->max('show_date')),
                    ];
                })->values()->all()
            );
        }

        $suspects = $shows->map(function (Show $show) {
            $stored = $this->extractUuid((string) $show->whatnot_show_id);
            $payloadUuids = $this->payloadUuids($show->raw_import_payload);

            $identity = $payloadUuids === []
                ? 'payload-no-uuid'
                : ($stored && in_array($stored, $payloadUuids, true) ? 'contains-stored-uuid' : 'MISMATCH');

            return ['show' => $show, 'identity' => $identity, 'payload_uuids' => $payloadUuids];
        });

        $identityProblems = $suspects->filter(fn (array $row) => $row['identity'] !== 'contains-stored-uuid');

        $this->newLine();
        $this->comment('Stored show UUID vs raw payload UUID evidence');
        $this->line('  raw payload contains stored UUID: ' . ($suspects->count() - $identityProblems->count()));
        $this->line('  payload missing UUID or mismatch: ' . $identityProblems->count());

        if ($identityProblems->isNotEmpty()) {
            $this->table(
                ['ID', 'Date', 'Gross', 'Stored UUID', 'Payload', 'Show'],
                $identityProblems->take(60)->map(function (array $row) {
                    /** @var Show $show */
                    $show = $row['show'];
                    return [
                        $show->id,
                        $show->show_date?->format('Y-m-d'),
                        '$' . number_format((float) $show->gross_revenue, 2),
                        $show->whatnot_show_id ?: '—',
                        $row['identity'] === 'MISMATCH'
                            ? implode(', ', array_slice($row['payload_uuids'], 0, 2))
                            : 'no UUID in payload',
                        mb_strimwidth((string) $show->title, 0, 42, '…'),
                    ];
                })->values()->all()
            );
        }

        if ($grossGroups->isNotEmpty()) {
            $this->newLine();
            $this->comment('Details for repeated gross groups');
            foreach ($grossGroups as $gross => $group) {
                $this->line('  $' . number_format((float) $gross, 2) . ' — ' . $group->count() . ' show(s)');
                $this->table(
                    ['ID', 'Date', 'UUID', 'Net', 'Units', 'Last analytics', 'Title'],
                    $group->map(fn (Show $show) => [
                        $show->id,
                        $show->show_date?->format('Y-m-d'),
                        $show->whatnot_show_id ?: '—',
                        '$' . number_format((float) ($show->whatnot_net ?? 0), 2),
                        (int) ($show->units_sold ?? 0),
                        $this->formatDateTime($show->last_analytics_synced_at),
                        mb_strimwidth((string) $show->title, 0, 42, '…'),
                    ])->values()->all()
                );
            }
        }

        $this->newLine();
        $this->info('Audit only — nothing was changed. Full clones and repeated Gross + Est Net pairs with varying units/buyers are treated as corruption signals.');

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

    private function formatDate(mixed $value): string
    {
        if ($value === null || $value === '') return '—';
        try { return Carbon::parse($value)->format('Y-m-d'); }
        catch (\Throwable) { return (string) $value; }
    }

    private function formatDateTime(mixed $value): string
    {
        if ($value === null || $value === '') return '—';
        try { return Carbon::parse($value)->format('Y-m-d H:i'); }
        catch (\Throwable) { return (string) $value; }
    }

    /** @return list<string> */
    private function payloadUuids(mixed $value): array
    {
        $found = [];
        $walk = function (mixed $node) use (&$walk, &$found): void {
            if (is_string($node)) {
                if (preg_match_all('/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i', $node, $matches)) {
                    foreach ($matches[0] as $uuid) $found[] = strtolower($uuid);
                }
                return;
            }
            if (! is_array($node)) return;
            foreach ($node as $child) $walk($child);
        };
        $walk($value);
        return array_values(array_unique($found));
    }

    private function extractUuid(string $value): ?string
    {
        return preg_match('/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i', $value, $m)
            ? strtolower($m[0])
            : null;
    }
}
