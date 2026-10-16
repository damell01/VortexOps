<?php

namespace App\Services;

use App\Models\Payout;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\StreamerLogEntry;
use App\Support\BusinessTransfer;
use App\Support\PayRunSchedules;
use Illuminate\Support\Collection;

/**
 * What-if payroll over real shows.
 *
 * Reads real shows, their reports and their people, lets any of the figures
 * the pay calculation reads be changed, and works the pay out with
 * PayoutService::previewForShow() — the same calculation a pay run uses.
 * Edited values live only on in-memory copies of the models; nothing is ever
 * saved. A field left at its real value is not applied at all, so an
 * unedited show is calculated from exactly the records payroll reads.
 */
class PayrollSimulation
{
    /** Per-show inputs: on the show itself. */
    public const SHOW_FIELDS = ['gross_revenue', 'whatnot_net', 'tips', 'house_cut_override'];

    /** Per-show inputs: on the show report. */
    public const REPORT_FIELDS = ['product_cost', 'hours_streamed', 'number_of_shipments', 'pwe_count', 'label_count'];

    /** Per-person inputs, as PaymentStructure resolves them. */
    public const RATE_FIELDS = [
        'payout_type', 'payout_percentage', 'house_cut_percentage', 'sales_split_basis', 'package_rate',
        'hourly_rate', 'pwe_rate', 'label_rate', 'include_tips', 'custom_payout_formula', 'burden_rate_type', 'burden_rate_value',
    ];

    private const INTEGER_FIELDS = ['number_of_shipments', 'pwe_count', 'label_count'];

    private const TEXT_FIELDS = ['payout_type', 'sales_split_basis', 'custom_payout_formula', 'burden_rate_type'];

    public function __construct(private PayoutService $payouts) {}

    /** Real shows in a date range, optionally limited to one business's or one person's. */
    public function shows(string $from, string $to, ?int $businessId = null, ?int $streamerId = null, int $limit = 60): Collection
    {
        return $this->query($from, $to, $businessId, $streamerId)
            ->with(['streamers', 'streamerLogEntry', 'channel'])
            ->orderBy('show_date')->orderBy('id')
            ->limit($limit)
            ->get();
    }

    public function countShows(string $from, string $to, ?int $businessId = null, ?int $streamerId = null): int
    {
        return $this->query($from, $to, $businessId, $streamerId)->count();
    }

    /** @param array<int> $ids */
    public function showsById(array $ids): Collection
    {
        if ($ids === []) {
            return collect();
        }

        return Show::query()->whereKey($ids)
            ->with(['streamers', 'streamerLogEntry', 'channel'])
            ->orderBy('show_date')->orderBy('id')
            ->get();
    }

    private function query(string $from, string $to, ?int $businessId, ?int $streamerId): \Illuminate\Database\Eloquent\Builder
    {
        return Show::query()
            ->whereDate('show_date', '>=', $from)
            ->whereDate('show_date', '<=', $to)
            ->where('status', '!=', 'cancelled')
            ->when($businessId, fn ($q) => $q->whereHas('streamers', fn ($s) => $s->where('streamers.business_id', $businessId)))
            ->when($streamerId, fn ($q) => $q->whereHas('streamers', fn ($s) => $s->where('streamers.id', $streamerId)));
    }

    /** The figures payroll reads for a show, as they stand. Null = not entered. @return array<string,mixed> */
    public function showInputs(Show $show): array
    {
        $log = $show->streamerLogEntry;
        $values = [];
        foreach (self::SHOW_FIELDS as $field) {
            $values[$field] = self::clean($field, $show->getAttribute($field));
        }
        foreach (self::REPORT_FIELDS as $field) {
            $values[$field] = self::clean($field, $log?->getAttribute($field));
        }

        return $values;
    }

    /** A person's rates exactly as the payout engine reads them. @return array<string,mixed> */
    public function rateInputs(Streamer $streamer): array
    {
        $values = [];
        foreach (self::RATE_FIELDS as $field) {
            $values[$field] = self::clean($field, $streamer->getAttribute($field));
        }

        return $values;
    }

    /** The fields of $inputs that differ from $real. @return array<string,mixed> */
    public static function edits(array $inputs, array $real): array
    {
        $edits = [];
        foreach ($real as $field => $value) {
            if (! array_key_exists($field, $inputs)) {
                continue;
            }
            $input = self::clean($field, $inputs[$field]);
            if (! self::same($input, $value)) {
                $edits[$field] = $input;
            }
        }

        return $edits;
    }

    /**
     * Pay for each show and person, as the inputs stand and as the real
     * records stand, plus the totals and the business transfer.
     *
     * @param  array<int, array<string,mixed>>  $showInputs  show id => inputs
     * @param  array<int, array<string,mixed>>  $rateInputs  streamer id => rates
     */
    public function run(Collection $shows, array $showInputs = [], array $rateInputs = []): array
    {
        $realRates = [];
        foreach ($shows as $show) {
            foreach ($show->streamers as $streamer) {
                $realRates[$streamer->id] ??= $this->rateInputs($streamer);
            }
        }
        $rateEdits = [];
        foreach ($realRates as $id => $real) {
            $rateEdits[$id] = self::edits($rateInputs[$id] ?? $real, $real);
        }

        $recorded = Payout::query()->whereIn('show_id', $shows->pluck('id'))->get()
            ->groupBy(fn (Payout $p) => $p->show_id . ':' . $p->streamer_id);

        $rows = [];
        $people = [];
        foreach ($shows as $show) {
            $realInputs = $this->showInputs($show);
            $showEdits = self::edits($showInputs[$show->id] ?? $realInputs, $realInputs);

            $real = collect($this->payouts->previewForShow($show))->keyBy(fn ($r) => $r['streamer']->id);
            $what = collect($this->payouts->previewForShow($this->copyShow($show, $showEdits, $rateEdits)))->keyBy(fn ($r) => $r['streamer']->id);

            $streamerRows = [];
            foreach ($show->streamers as $streamer) {
                $r = $real->get($streamer->id);
                $w = $what->get($streamer->id);
                $lines = collect($w['lines'] ?? [])->map(fn (array $line) => [
                    'type' => $line['payout_type'],
                    'label' => Streamer::payoutTypeLabels()[$line['payout_type']] ?? $line['payout_type'],
                    'amount' => round((float) $line['calculated_payout'], 2),
                    'hours' => $line['hours_worked'] ?? null,
                    'notes' => $line['calculation_notes'] ?? '',
                    'transfer_counts' => ! in_array($line['payout_type'], PayRunSchedules::TIP_TYPES, true),
                ])->all();
                $saved = $recorded->get($show->id . ':' . $streamer->id);

                $streamerRows[] = [
                    'id' => $streamer->id,
                    'name' => $streamer->name,
                    'is_primary' => (bool) ($w['is_primary'] ?? false),
                    'lines' => $lines,
                    'total' => (float) ($w['total'] ?? 0),
                    'real_total' => (float) ($r['total'] ?? 0),
                    'delta' => round((float) ($w['total'] ?? 0) - (float) ($r['total'] ?? 0), 2),
                    'pay_excl_tips' => round((float) collect($lines)->where('transfer_counts', true)->sum('amount'), 2),
                    'real_pay_excl_tips' => self::payExcludingTips($r['lines'] ?? []),
                    'error' => $w['error'] ?? null,
                    'real_error' => $r['error'] ?? null,
                    'edited' => $rateEdits[$streamer->id] !== [],
                    'recorded' => $saved ? [
                        'amount' => round((float) $saved->sum('calculated_payout'), 2),
                        'state' => $saved->every(fn (Payout $p) => $p->status === 'paid') ? 'paid'
                            : ($saved->every(fn (Payout $p) => $p->status === 'draft') ? 'draft' : 'final'),
                    ] : null,
                ];

                $people[$streamer->id] ??= [
                    'id' => $streamer->id, 'name' => $streamer->name,
                    'business_id' => $streamer->getRawOriginal('business_id'),
                    'shows' => 0, 'total' => 0.0, 'real_total' => 0.0, 'errors' => 0,
                    'edited' => $rateEdits[$streamer->id] !== [],
                    'payout_type' => ($rateInputs[$streamer->id] ?? $realRates[$streamer->id])['payout_type'] ?? null,
                    'real_payout_type' => $realRates[$streamer->id]['payout_type'] ?? null,
                ];
                $people[$streamer->id]['shows']++;
                $people[$streamer->id]['total'] = round($people[$streamer->id]['total'] + (float) ($w['total'] ?? 0), 2);
                $people[$streamer->id]['real_total'] = round($people[$streamer->id]['real_total'] + (float) ($r['total'] ?? 0), 2);
                $people[$streamer->id]['errors'] += ($w['error'] ?? null) ? 1 : 0;
            }

            $scenario = array_replace($realInputs, $showEdits);
            $total = round((float) collect($streamerRows)->sum('total'), 2);
            $realTotal = round((float) collect($streamerRows)->sum('real_total'), 2);

            $rows[] = [
                'id' => $show->id,
                'title' => $show->title ?: "Show #{$show->id}",
                'date' => $show->show_date,
                'channel' => $show->channel?->name,
                'status' => $show->status,
                'has_report' => $show->streamerLogEntry !== null,
                'inputs' => $scenario,
                'real' => $realInputs,
                'edits' => $showEdits,
                'streamers' => $streamerRows,
                'total' => $total,
                'real_total' => $realTotal,
                'delta' => round($total - $realTotal, 2),
                'net' => (float) ($scenario['whatnot_net'] ?? 0),
                'real_net' => (float) ($realInputs['whatnot_net'] ?? 0),
                'product_cost' => (float) ($scenario['product_cost'] ?? 0),
                'real_product_cost' => (float) ($realInputs['product_cost'] ?? 0),
                'pay_excl_tips' => round((float) collect($streamerRows)->sum('pay_excl_tips'), 2),
                'real_pay_excl_tips' => round((float) collect($streamerRows)->sum('real_pay_excl_tips'), 2),
            ];
        }

        $rows = collect($rows);
        foreach ($people as &$person) {
            $person['delta'] = round($person['total'] - $person['real_total'], 2);
        }
        unset($person);

        $transfer = BusinessTransfer::fromTotals($rows->count(), (float) $rows->sum('product_cost'), (float) $rows->sum('net'), (float) $rows->sum('pay_excl_tips'));
        $realTransfer = BusinessTransfer::fromTotals($rows->count(), (float) $rows->sum('real_product_cost'), (float) $rows->sum('real_net'), (float) $rows->sum('real_pay_excl_tips'));

        return [
            'rows' => $rows->all(),
            'people' => collect($people)->sortBy('name')->values()->all(),
            'total' => round((float) $rows->sum('total'), 2),
            'real_total' => round((float) $rows->sum('real_total'), 2),
            'delta' => round((float) $rows->sum('total') - (float) $rows->sum('real_total'), 2),
            'transfer' => $transfer,
            'real_transfer' => $realTransfer,
            'edited_shows' => $rows->filter(fn ($r) => $r['edits'] !== [])->count(),
            'edited_people' => collect($rateEdits)->filter()->count(),
        ];
    }

    /**
     * An in-memory copy of the show carrying the edited values, its report
     * and its people. Fields that were not edited are not touched.
     */
    private function copyShow(Show $show, array $showEdits, array $rateEdits): Show
    {
        $copy = clone $show;
        foreach (array_intersect_key($showEdits, array_flip(self::SHOW_FIELDS)) as $field => $value) {
            $copy->setAttribute($field, $value);
        }

        $reportEdits = array_intersect_key($showEdits, array_flip(self::REPORT_FIELDS));
        $log = $show->streamerLogEntry;
        if ($reportEdits !== []) {
            // A show with no report filed gets an unsaved one holding just the edits.
            $log = $log ? clone $log : new StreamerLogEntry(['show_id' => $show->id]);
            foreach ($reportEdits as $field => $value) {
                $log->setAttribute($field, $value);
            }
        }
        $copy->setRelation('streamerLogEntry', $log);

        $copy->setRelation('streamers', $show->streamers->map(function (Streamer $streamer) use ($rateEdits) {
            $edits = $rateEdits[$streamer->id] ?? [];
            if ($edits === []) {
                return $streamer;
            }
            $person = clone $streamer;
            // A blank rate is $0 here, not "fall back to the stored column",
            // and clearing the burden type clears the burden.
            foreach ($edits as $field => $value) {
                if ($value !== null) {
                    continue;
                }
                $edits[$field] = match ($field) {
                    'custom_payout_formula' => '',
                    'burden_rate_type', 'sales_split_basis' => null,
                    default => 0.0,
                };
                if ($field === 'burden_rate_type') {
                    $edits['burden_rate_value'] = 0.0;
                }
            }
            $person->whatIfCompensation = $edits;

            return $person;
        })->values());

        return $copy;
    }

    private static function payExcludingTips(array $lines): float
    {
        return round((float) collect($lines)
            ->reject(fn (array $line) => in_array($line['payout_type'] ?? null, PayRunSchedules::TIP_TYPES, true))
            ->sum('calculated_payout'), 2);
    }

    /** One value as the simulator compares it: null when blank, else float / int / bool / string. */
    public static function clean(string $field, mixed $value): mixed
    {
        if ($field === 'include_tips') {
            return (bool) $value;
        }
        if (in_array($field, self::TEXT_FIELDS, true)) {
            $value = $value === null ? null : trim((string) $value);

            return $value === '' ? null : $value;
        }
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        return in_array($field, self::INTEGER_FIELDS, true) ? (int) round((float) $value) : round((float) $value, 4);
    }

    private static function same(mixed $a, mixed $b): bool
    {
        if (is_float($a) || is_float($b)) {
            return $a !== null && $b !== null && abs((float) $a - (float) $b) < 0.00005;
        }

        return $a === $b;
    }
}
