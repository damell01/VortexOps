<?php

namespace App\Services;

use App\Models\Payout;
use App\Models\Setting;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\StreamerLogEntry;
use App\Models\WeeklyPayoutBatch;
use App\Support\ProfitShareFormula;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PayoutService
{
    /** Pay lines kept apart from a person's main line for a show: type => key on the computed result. */
    public const SPLIT_LINES = ['show_hours' => 'split_hourly', 'show_tips' => 'split_tips'];

    public function calculateForShow(Show $show): array
    {
        $streamers = $show->streamers;

        if ($streamers->isEmpty()) {
            return [];
        }

        $payouts = [];

        $allExisting = Payout::with('batch')->where('show_id', $show->id)
            ->whereIn('streamer_id', $streamers->pluck('id'))
            ->get();
        // Wages and profit share always remain separate pay lines, and so do
        // tips a business pays monthly. SPLIT_LINES names those extra lines.
        $existingPayouts = $allExisting->whereNotIn('payout_type', array_keys(self::SPLIT_LINES))->keyBy('streamer_id');
        $existingSplits = collect(self::SPLIT_LINES)->map(fn ($key, $type) => $allExisting->where('payout_type', $type)->keyBy('streamer_id'));

        $editable = fn (?Payout $p) => $p && $p->status === 'draft' && (! $p->batch || $p->batch->status === 'draft');

        foreach ($streamers as $index => $streamer) {
            $existing = $existingPayouts->get($streamer->id);
            // A calculation for a new wage/share run must never rewrite pay
            // already submitted, finalized or paid on another run.
            $protectedMain = $existing && ! $editable($existing);
            $hasSplit = $existingSplits->contains(fn ($lines) => $lines->has($streamer->id));
            if ($protectedMain && ! $hasSplit) {
                $payouts[] = $existing;
                continue;
            }

            $isPrimary = $this->isPrimaryOnShow($streamers, $streamer, $index);

            $result = $this->computeStreamerPayout($streamer, $show, $streamers->count(), $isPrimary);
            $splitLines = [];
            foreach (self::SPLIT_LINES as $type => $key) {
                $splitLines[$type] = $result[$key] ?? null;
                unset($result[$key]);
            }

            $payout = $existing
                ? ($protectedMain ? $existing : $existing->fill($result))
                : new Payout(array_merge($result, [
                    'show_id'     => $show->id,
                    'streamer_id' => $streamer->id,
                ]));

            if (! $protectedMain) {
                $payout->save();
            }
            $payouts[] = $payout;

            foreach ($splitLines as $type => $line) {
                $splitPayout = $existingSplits[$type]->get($streamer->id);
                if ($line) {
                    $splitPayout ??= new Payout(['show_id' => $show->id, 'streamer_id' => $streamer->id]);
                    if (! $splitPayout->exists || $editable($splitPayout)) {
                        $splitPayout->fill($line + [
                            'payout_type' => $type,
                            'gross_show_revenue' => 0,
                            'owner_fee_deducted' => 0,
                            'tips_included' => 0,
                            'routing_bank_label' => $result['routing_bank_label'] ?? null,
                            'status' => 'draft',
                        ])->save();
                    }
                    $payouts[] = $splitPayout;
                } elseif ($editable($splitPayout)) {
                    $splitPayout->delete();
                }
            }
        }

        return $payouts;
    }

    /**
     * Whether this person carries the revenue share on the show.
     *
     * Prefer the pivot's actual is_primary flag. Shows that predate that
     * flag (or where detectStreamers() never set one) have no streamer
     * flagged primary at all — fall back to treating the first attached
     * streamer as primary so solo-streamer shows and older data keep
     * computing exactly as before.
     */
    private function isPrimaryOnShow(\Illuminate\Support\Collection $streamers, Streamer $streamer, int $index): bool
    {
        $hasExplicitPrimary = $streamers->contains(fn (Streamer $s) => (bool) ($s->pivot->is_primary ?? false));

        return $hasExplicitPrimary
            ? (bool) ($streamer->pivot->is_primary ?? false)
            : $index === 0;
    }

    /**
     * The pay calculateForShow() works out for each person on a show, without
     * reading or writing a single payout — the Payroll Simulator's engine.
     *
     * It runs the same computeStreamerPayout() a pay run does, so a what-if
     * cannot drift from real payroll. The show, its report and its people
     * may be in-memory copies carrying edited values (see
     * App\Services\PayrollSimulation); nothing here saves them. Unlike
     * calculateForShow(), pay already locked on a finalized run is worked
     * out afresh rather than read back, since the question is what the
     * calculation gives for these inputs.
     *
     * Expects show.streamers (with pivot), show.streamerLogEntry and
     * show.channel loaded.
     *
     * @return array<int, array{streamer: Streamer, is_primary: bool, lines: array<int, array<string, mixed>>, total: float, error: ?string}>
     */
    public function previewForShow(Show $show): array
    {
        $streamers = $show->streamers->values();
        $rows = [];

        foreach ($streamers as $index => $streamer) {
            $isPrimary = $this->isPrimaryOnShow($streamers, $streamer, $index);

            try {
                $result = $this->computeStreamerPayout($streamer, $show, $streamers->count(), $isPrimary);
            } catch (\Throwable $e) {
                $rows[] = ['streamer' => $streamer, 'is_primary' => $isPrimary, 'lines' => [], 'total' => 0.0, 'error' => $e->getMessage()];
                continue;
            }

            $splits = [];
            foreach (self::SPLIT_LINES as $type => $key) {
                if ($result[$key] ?? null) {
                    $splits[] = $result[$key] + ['payout_type' => $type];
                }
                unset($result[$key]);
            }
            $lines = [$result, ...$splits];

            $rows[] = [
                'streamer' => $streamer,
                'is_primary' => $isPrimary,
                'lines' => $lines,
                'total' => round((float) collect($lines)->sum('calculated_payout'), 2),
                'error' => null,
            ];
        }

        return $rows;
    }

    /**
     * What a report's streamer is owed for that one show, for display.
     *
     * Pay already on a run is read back as it stands (draft, finalized or
     * paid); otherwise it is worked out with the same calculation the run
     * uses, without saving anything. Wages split onto their own line are
     * included, so this is the whole show.
     *
     * Expects show.streamers, show.channel and streamer loaded.
     *
     * @return array{amount:float, state:string, notes:string}|null  state: estimate|draft|final|paid
     */
    public function payForReport(StreamerLogEntry $entry, ?\Illuminate\Support\Collection $payouts = null): ?array
    {
        $show = $entry->show;
        $streamer = $entry->streamer;
        if (! $show || ! $streamer) {
            return null;
        }

        $payouts ??= Payout::where('show_id', $show->id)->where('streamer_id', $streamer->id)->get();
        if ($payouts->isNotEmpty()) {
            $state = $payouts->every(fn (Payout $p) => $p->status === 'paid') ? 'paid'
                : ($payouts->every(fn (Payout $p) => $p->status === 'draft') ? 'draft' : 'final');

            return [
                'amount' => round((float) $payouts->sum('calculated_payout'), 2),
                'state' => $state,
                'notes' => $payouts->pluck('calculation_notes')->filter()->implode(' · '),
            ];
        }

        $streamers = $show->streamers;
        $hasPrimary = $streamers->contains(fn (Streamer $s) => (bool) ($s->pivot->is_primary ?? false));
        $member = $streamers->firstWhere('id', $streamer->id);
        $isPrimary = $hasPrimary ? (bool) ($member?->pivot->is_primary ?? false) : $streamers->first()?->id === $streamer->id;

        try {
            $show->setRelation('streamerLogEntry', $entry);
            $result = $this->computeStreamerPayout($streamer, $show, max(1, $streamers->count()), $isPrimary);
        } catch (\Throwable) {
            return null;
        }

        $splits = collect(self::SPLIT_LINES)->map(fn ($key) => $result[$key] ?? null)->filter();

        return [
            'amount' => round((float) $result['calculated_payout'] + (float) $splits->sum('calculated_payout'), 2),
            'state' => 'estimate',
            'notes' => trim(collect([$result['calculation_notes']])->merge($splits->pluck('calculation_notes'))->implode(' · ')),
        ];
    }

    private function computeStreamerPayout(Streamer $streamer, Show $show, int $streamerCount, bool $isPrimary = false): array
    {
        $netRevenue    = (float) $show->whatnot_net;
        $grossRevenue  = (float) $show->gross_revenue;
        $tips          = (float) $show->tips;
        // On a collab show, the primary streamer keeps the full revenue share
        // for profit-share-based calculations (profit_share, hybrid's profit
        // component, custom_formula's streamer_share_net) — splitting with
        // collaborators is handled manually outside the system, not by
        // dividing it automatically. Non-primary streamers' other payout
        // components (hourly, PWE/labels, package, flat rate) are unaffected,
        // since none of those read $streamerShare.
        $streamerShare = ($isPrimary || $streamerCount <= 1) ? $netRevenue : 0.0;
        $tipShare      = $streamerCount > 0 ? round($tips / $streamerCount, 2) : 0;
        // Give any sub-cent rounding remainder to the primary streamer (e.g. $10 ÷ 3 = $3.33×3 = $9.99; primary gets $3.34)
        if ($isPrimary && $streamerCount > 1 && $tips > 0) {
            $tipShare = round($tips - round($tips / $streamerCount, 2) * ($streamerCount - 1), 2);
        }

        $calculatedPayout = 0;
        $calculationNotes = '';

        $pweCount   = 0;
        $labelCount = 0;
        $burdenRateApplied = null;

        // A show has exactly one report — streamer_log_entries.show_id is
        // unique — so on a collab both people's payouts read the same product
        // cost and hours. That is the schema's decision, not this method's.
        $logEntry = $show->relationLoaded('streamerLogEntry')
            ? $show->getRelation('streamerLogEntry')
            : $show->streamerLogEntry()->first();

        // Hours Worked, never the show's length on Whatnot: the report's
        // figure, else the time this person clocked against the show. With
        // neither, the hours are missing — no hourly pay, and the pay run's
        // sign-off names the show.
        $workedHours = $this->workedHours($streamer, $show, $logEntry);
        $hours = $workedHours ?? 0.0;
        $hoursMissingNote = $workedHours === null ? ' · Hours missing' : '';

        $share = null;
        $shiftWages = null;

        switch ($streamer->payout_type) {
            case 'profit_share':
                $share            = $this->profitShareWorking($show, $streamer, $logEntry, $hours, $isPrimary || $streamerCount <= 1);
                $calculatedPayout = $share['earnings'];
                $notPrimaryNote   = $streamerCount > 1 && ! $isPrimary
                    ? ' — non-primary on collab show, revenue share goes to the primary streamer; split manually outside VortexOps'
                    : '';

                $calculationNotes = $share['note'] . $notPrimaryNote . $hoursMissingNote;

                if ($streamer->include_tips) {
                    $calculatedPayout = round($calculatedPayout + $tipShare, 2);
                    $calculationNotes .= " + \${$tipShare} tips";
                }
                break;

            case 'sales_split':
                // Estill Distribution's arrangement: the business keeps a set
                // share of what the streamer's show earned and the streamer
                // gets the rest. Like profit share, on a collab the primary
                // streamer carries the split.
                $basisKey  = $streamer->sales_split_basis === 'gross' ? 'gross' : 'net';
                $basis     = ($isPrimary || $streamerCount <= 1) ? ($basisKey === 'gross' ? $grossRevenue : $netRevenue) : 0.0;
                // A special show can set its own %, overriding the streamer's.
                $showCut   = $show->house_cut_override;
                $houseCut  = min(100.0, max(0.0, (float) ($showCut ?? $streamer->house_cut_percentage ?? 0)));
                $calculatedPayout = round($basis * (100 - $houseCut) / 100, 2);
                $basisLabel = $basisKey === 'gross' ? 'gross sales' : 'estimated net earnings';
                $calculationNotes = 'Sales split: $' . number_format($basis, 2) . " {$basisLabel} − {$houseCut}% kept by the business"
                    . ($showCut !== null ? ' (this show\'s %)' : '');
                if ($streamerCount > 1 && ! $isPrimary) {
                    $calculationNotes .= ' — non-primary on collab show, the split goes to the primary streamer';
                }
                if ($streamer->include_tips) {
                    $calculatedPayout = round($calculatedPayout + $tipShare, 2);
                    $calculationNotes .= " + \${$tipShare} tips";
                }
                $share = ['columns' => ['net_revenue_basis' => $basis]];
                break;

            case 'shift_share':
                // Estill Distribution's hourly streamers: paid for their hours,
                // for each shipment (envelopes and packages at their own
                // rates), and a share of what is left of the show once those,
                // the product and any burden are paid for:
                //   (net sales − product cost − burden − hourly − shipment pay) × share %
                //
                // Hours and shipments are wages, on a line of their own; this
                // line is the profit share. An all-pay run pays both (Estill,
                // weekly); separate wage and profit-share runs can pay them on
                // different schedules, as VCX does.
                $shift = $this->shiftShareWorking($show, $streamer, $logEntry, $hours, $isPrimary || $streamerCount <= 1);
                $calculatedPayout = $shift['columns']['share'];
                $calculationNotes = $shift['note'] . $hoursMissingNote;
                $shiftWages = $shift['columns']['hourly_pay'] + $shift['columns']['shipment_pay'] > 0 ? [
                    'calculated_payout' => round($shift['columns']['hourly_pay'] + $shift['columns']['shipment_pay'], 2),
                    'hours_worked' => $hours,
                    'pwe_count' => $shift['pwe'] ?: null,
                    'label_count' => $shift['packages'] ?: null,
                    'calculation_notes' => 'Hourly and shipments for this show: $' . number_format($shift['columns']['hourly_pay'], 2) . ' hourly + $'
                        . number_format($shift['columns']['shipment_pay'], 2) . ' shipments. The profit share is its own line.',
                ] : null;
                $pweCount = $shift['pwe'];
                $labelCount = $shift['packages'];
                if ($streamer->include_tips) {
                    $calculatedPayout = round($calculatedPayout + $tipShare, 2);
                    $calculationNotes .= " + \${$tipShare} tips";
                }
                $share = ['columns' => $shift['columns']];
                break;

            case 'package':
                $calculatedPayout = (float) $streamer->package_rate;
                $calculationNotes = "Package rate \${$streamer->package_rate}";
                if ($streamer->include_tips) {
                    $calculatedPayout += $tipShare;
                    $calculationNotes .= " + \${$tipShare} tips";
                }
                break;

            case 'hourly':
                $calculatedPayout = round((float) $streamer->hourly_rate * $hours, 2);
                $calculationNotes = "Hourly rate \${$streamer->hourly_rate}/hr × {$hours}hrs worked" . $hoursMissingNote;
                break;

            case 'flat_rate':
                if ($streamer->package_rate === null) {
                    throw new \RuntimeException("Streamer #{$streamer->id} ({$streamer->name}) has payout_type=flat_rate but package_rate is not set.");
                }
                $calculatedPayout = (float) $streamer->package_rate;
                $calculationNotes = "Flat rate \${$calculatedPayout}";
                break;

            case 'pwe_labels':
                // Per-package (PWE) + per-label model, with optional hourly component.
                // Prefer the real counts fulfillment/the streamer entered on the log
                // entry; fall back to units_sold as a rough estimate only when those
                // haven't been entered yet.
                $countsAreActual = $logEntry && ($logEntry->pwe_count !== null || $logEntry->label_count !== null);
                $pweCount   = $logEntry?->pwe_count   ?? (int) ($show->units_sold ?? 0);
                $labelCount = $logEntry?->label_count ?? (int) ($show->units_sold ?? 0);
                $pweEarned    = round((float) ($streamer->pwe_rate ?? 0) * $pweCount, 2);
                $labelEarned  = round((float) ($streamer->label_rate ?? 0) * $labelCount, 2);
                $hourlyEarned = $hours > 0 ? round((float) ($streamer->hourly_rate ?? 0) * $hours, 2) : 0;
                $calculatedPayout = $pweEarned + $labelEarned + $hourlyEarned;
                $calculationNotes = "\${$streamer->pwe_rate}/PWE × {$pweCount} + \${$streamer->label_rate}/label × {$labelCount}";
                if (! $countsAreActual) {
                    $calculationNotes .= ' (estimated from units sold — no fulfillment counts entered yet)';
                }
                if ($hourlyEarned > 0) {
                    $calculationNotes .= " + \${$streamer->hourly_rate}/hr × {$hours}hrs";
                } elseif ((float) ($streamer->hourly_rate ?? 0) > 0) {
                    $calculationNotes .= $hoursMissingNote;
                }
                if ($streamer->include_tips) {
                    $calculatedPayout += $tipShare;
                    $calculationNotes .= " + \${$tipShare} tips";
                }
                break;

            case 'hybrid':
                // Hourly base + tips share + profit share component, with optional burden rate.
                //
                // The profit component reads the same basis as a plain
                // profit_share payout. It used to be worked out off whatnot_net
                // while profit_share used the calculations sheet, so the same
                // words on two people's records paid two different formulas.
                $hourlyBase   = $hours > 0 ? round((float) ($streamer->hourly_rate ?? 0) * $hours, 2) : 0;
                $share        = $this->profitShareWorking($show, $streamer, $logEntry, $hours, $isPrimary || $streamerCount <= 1);
                $profitComp   = $share['earnings'];
                $base         = $hourlyBase + $profitComp + ($streamer->include_tips ? $tipShare : 0);

                if ($streamer->burden_rate_type && (float) ($streamer->burden_rate_value ?? 0) > 0) {
                    $burdenRateApplied = $streamer->burden_rate_type === 'percentage'
                        ? round($base * ((float) $streamer->burden_rate_value / 100), 4)
                        : (float) $streamer->burden_rate_value;
                    $base += $burdenRateApplied;
                }

                $calculatedPayout = round($base, 2);
                $calculationNotes = "Hybrid: \${$hourlyBase} hourly ({$hours}hrs){$hoursMissingNote} + {$streamer->payout_percentage}% profit (\${$profitComp})";
                if ($streamer->include_tips) {
                    $calculationNotes .= " + \${$tipShare} tips";
                }
                if ($burdenRateApplied) {
                    $calculationNotes .= " + \${$burdenRateApplied} burden";
                }
                break;

            case 'custom_formula':
                $formula = trim((string) $streamer->custom_payout_formula);
                $calculatedPayout = $formula !== ''
                    ? round($this->evaluateCustomFormula($formula, [
                        'gross_revenue'         => $grossRevenue,
                        'whatnot_net'           => $netRevenue,
                        'streamer_share_net'    => $streamerShare,
                        'units_sold'            => (float) $show->units_sold,
                        'show_duration_hours'   => $show->show_duration ? round($show->show_duration / 60, 2) : 0,
                        'show_duration_minutes' => (float) ($show->show_duration ?? 0),
                        'hours_worked'          => $hours,
                        'tips'                  => $tips,
                        'tip_share'             => $tipShare,
                        'payout_percentage'     => (float) ($streamer->payout_percentage ?? 0),
                        'package_rate'          => (float) ($streamer->package_rate ?? 0),
                        'hourly_rate'           => (float) ($streamer->hourly_rate ?? 0),
                        'pwe_rate'              => (float) ($streamer->pwe_rate ?? 0),
                        'label_rate'            => (float) ($streamer->label_rate ?? 0),
                    ]), 2)
                    : 0;
                $calculationNotes = "Custom formula: {$formula}";
                break;
        }

        // A business that pays tips monthly takes them off this line onto
        // their own, which only the monthly Tips run pays.
        $splitTips = null;
        $tipsIncluded = $streamer->include_tips ? $tipShare : 0;
        if ($tipsIncluded > 0 && $this->tipsPaidMonthly($streamer)) {
            $calculatedPayout = round($calculatedPayout - $tipsIncluded, 2);
            $calculationNotes .= " − \${$tipsIncluded} tips (paid monthly)";
            $splitTips = [
                'calculated_payout' => $tipsIncluded,
                'tips_included' => $tipsIncluded,
                'calculation_notes' => "Tips for this show: \${$tipsIncluded} (paid on the monthly tips run)",
            ];
            $tipsIncluded = 0;
        }

        // Owner fee — calculated against the gross payout before deduction.
        // A streamer's own fee override always wins; if they don't have one
        // set, fall back to the global default from Settings so the owner
        // doesn't have to configure the same fee on every streamer by hand.
        [$feeType, $feeValue, $feeDeductFromPayout] = $streamer->owner_fee_type
            ? [$streamer->owner_fee_type, (float) $streamer->owner_fee_value, $streamer->owner_fee_deduct_from_payout]
            : $this->defaultOwnerFee();

        $ownerFeeDeducted = 0;
        if ($feeType && $feeValue > 0) {
            $ownerFeeDeducted = $feeType === 'percentage'
                ? round($calculatedPayout * ($feeValue / 100), 2)
                : $feeValue;

            if ($feeDeductFromPayout) {
                $calculatedPayout = max(0, round($calculatedPayout - $ownerFeeDeducted, 2));
                $calculationNotes .= " − \${$ownerFeeDeducted} owner fee";
            }
        }

        // In-house streamers are also paid by the hour. Added after the owner
        // fee, which is a cut of the streamer's share, not of their wages.
        // Hours are the ones the share was worked out from (the streamer's
        // report, falling back to the show's length).
        $hourlyPay = 0.0;
        $splitHourly = $shiftWages ?? null;
        $hourlyRate = $streamer->paidHourlyRate();
        if ($hourlyRate !== null) {
            $paidHours = (float) ($share['columns']['hours_worked'] ?? $hours);
            if ($paidHours > 0) {
                $hourlyPay = round($hourlyRate * $paidHours, 2);
                if (\App\Support\PayRunSchedules::kindOfType($streamer->payout_type) === 'share') {
                    // Profit share is paid on its own schedule; the hours go
                    // on the wage run as their own line.
                    $splitHourly = [
                        'calculated_payout' => $hourlyPay,
                        'hours_worked' => $paidHours,
                        'calculation_notes' => "Hourly for this show: \${$hourlyRate}/hr × {$paidHours}hrs (separate wage line)",
                    ];
                } else {
                    $calculatedPayout = round($calculatedPayout + $hourlyPay, 2);
                    $calculationNotes .= " + \${$hourlyPay} hourly (\${$hourlyRate}/hr × {$paidHours}hrs)";
                }
            } elseif ($workedHours === null) {
                $calculationNotes .= ' · Hours missing — no hourly pay';
            }
        }

        return [
            'payout_type'          => $streamer->payout_type,
            'gross_show_revenue'   => $grossRevenue,
            // The inputs the share was worked out from, so the payout can show
            // its working without anybody reopening the report to re-do it.
            'product_cost'         => $share['columns']['product_cost']      ?? null,
            'hours_worked'         => $workedHours === null ? null : ($share['columns']['hours_worked'] ?? ($hourlyPay > 0 ? $paidHours : $workedHours)),
            'shipments_count'      => $share['columns']['shipments_count']   ?? null,
            'burden_amount'        => $share['columns']['burden_amount']     ?? null,
            'net_revenue_basis'    => $share['columns']['net_revenue_basis'] ?? null,
            'owner_fee_deducted'   => $ownerFeeDeducted,
            'tips_included'        => $tipsIncluded,
            'pwe_count'            => $pweCount > 0 ? $pweCount : null,
            'label_count'          => $labelCount > 0 ? $labelCount : null,
            'burden_rate_applied'  => $burdenRateApplied,
            'calculated_payout'    => $calculatedPayout,
            'calculation_notes'    => $calculationNotes,
            'routing_bank_label'   => $this->resolveRoutingLabel($streamer, $show),
            'status'               => 'draft',
            'split_hourly'         => $splitHourly,
            'split_tips'           => $splitTips,
        ];
    }

    /**
     * The global owner-fee fallback, set once in Settings instead of on every
     * streamer. Returns [type, value, deductFromPayout]; type is null when no
     * default is configured.
     *
     * @return array{0: ?string, 1: float, 2: bool}
     */
    /**
     * A profit-share payout, worked out the way the calculations sheet does.
     *
     * Until this existed the payout engine paid `whatnot_net × percentage` —
     * Whatnot's own net, with no product cost and no burden — while every
     * streamer filled in a sheet that subtracts both. The two disagreed by
     * about 15% on the show the formula was lifted from, and payroll corrected
     * it by hand every week.
     *
     * The inputs come from the streamer's own show report, because that is
     * where a person actually establishes what a show cost and how long it
     * ran. Hours fall back to the show's recorded length and shipments to its
     * shipment list, which is what the report pre-fills them from anyway.
     *
     * @param  bool  $countsTowardShare  False for a non-primary streamer on a
     *                                   collab show — the revenue share goes to
     *                                   the primary and is split outside here.
     * @return array{earnings:float,note:string,columns:array<string,float|int|null>}
     */
    private function profitShareWorking(
        Show $show,
        Streamer $streamer,
        ?StreamerLogEntry $logEntry,
        float $hours,
        bool $countsTowardShare,
    ): array {
        $percentage = (float) ($streamer->payout_percentage ?? 0);

        if (! $countsTowardShare) {
            return [
                'earnings' => 0.0,
                'note'     => "Profit share {$percentage}% of \$0.00",
                'columns'  => [],
            ];
        }

        // One formula, the sheet's, whatever the report does or does not say.
        // There was a second path here that valued an unreported show off
        // whatnot_net instead — safer-looking, but it meant two shows in the
        // same pay run could be worked out different ways with nothing on the
        // screen to say which was which.
        //
        // A blank cell on the sheet is a zero, so a missing product cost is a
        // zero here too. That reads as a show whose stock cost nothing, which
        // is exactly what an unreported show looks like until somebody files
        // it — and the sign-off gate names it before the run can be finalised.
        $productCost = (float) ($logEntry?->product_cost ?? 0);
        $hoursWorked = $hours;
        $shipments   = $logEntry?->number_of_shipments !== null
            ? (int) $logEntry->number_of_shipments
            : (int) $show->shipments()->count();

        $working = ProfitShareFormula::forShow(
            grossRevenue: (float) $show->gross_revenue,
            productCost:  $productCost,
            hours:        $hoursWorked,
            shipments:    $shipments,
            percentage:   $percentage,
        );

        return [
            'earnings' => $working['earnings'],
            'note'     => ProfitShareFormula::explain($working)
                . ($logEntry ? '' : ' No show report filed, so product cost counted as $0.00.'),
            'columns'  => [
                'product_cost'      => $working['product_cost'],
                'hours_worked'      => $working['hours'],
                'shipments_count'   => (int) $working['shipments'],
                'burden_amount'     => $working['burden'],
                'net_revenue_basis' => $working['net_revenue'],
            ],
        ];
    }

    /** @var array<int, bool> business id => pays tips monthly, for this request */
    private array $tipsMonthlyMemo = [];

    /** Whether this person's business pays tips on a monthly run of their own. */
    public function tipsPaidMonthly(Streamer $streamer): bool
    {
        $businessId = (int) $streamer->getRawOriginal('business_id');
        if ($businessId === 0) {
            return false;
        }

        return $this->tipsMonthlyMemo[$businessId] ??= (bool) \App\Models\Business::whereKey($businessId)->value('tips_monthly');
    }

    /**
     * Hourly + shipments + profit share, worked out in full.
     *
     * Shipments are split into envelopes (PWE) and packages (labels), each at
     * its own rate. Counts come from the show report; with no split entered,
     * every shipment counts as a package and the note says so.
     *
     * The burden is the person's flat $ per show, or a % of net sales.
     * The share is never negative: a show that does not cover its costs pays
     * the hours and shipments only, and the note says the share was $0.
     *
     * @return array{earnings:float,note:string,pwe:int,packages:int,columns:array<string,float|int|null>}
     */
    public function shiftShareWorking(Show $show, Streamer $streamer, ?StreamerLogEntry $logEntry, float $hours, bool $countsTowardShare): array
    {
        $rate = (float) ($streamer->hourly_rate ?? 0);
        $pweRate = (float) ($streamer->pwe_rate ?? 0);
        $packageRate = (float) ($streamer->label_rate ?? 0);
        $pct = (float) ($streamer->payout_percentage ?? 0);

        $split = $logEntry && ($logEntry->pwe_count !== null || $logEntry->label_count !== null);
        $shipments = $logEntry?->number_of_shipments !== null ? (int) $logEntry->number_of_shipments : (int) $show->shipments()->count();
        $pwe = $split ? (int) ($logEntry->pwe_count ?? 0) : 0;
        $packages = $split ? (int) ($logEntry->label_count ?? 0) : $shipments;

        $hourly = round($rate * $hours, 2);
        $shipping = round($pwe * $pweRate + $packages * $packageRate, 2);
        $netSales = round((float) $show->whatnot_net, 2);
        $productCost = round((float) ($logEntry?->product_cost ?? 0), 2);
        $burdenValue = (float) ($streamer->burden_rate_value ?? 0);
        $burden = $burdenValue > 0
            ? round($streamer->burden_rate_type === 'percentage' ? $netSales * $burdenValue / 100 : $burdenValue, 2)
            : 0.0;

        $left = round($netSales - $productCost - $burden - $hourly - $shipping, 2);
        $share = $countsTowardShare ? max(0.0, round($left * $pct / 100, 2)) : 0.0;

        $money = fn (float $v) => '$' . number_format($v, 2);
        $note = "Hourly {$money($rate)}/hr × {$hours}hrs = {$money($hourly)}"
            . " + shipments {$pwe} envelopes × {$money($pweRate)} + {$packages} packages × {$money($packageRate)} = {$money($shipping)}"
            . ($split ? '' : ' (no envelope/package split entered — all counted as packages)')
            . ". Share: ({$money($netSales)} net − {$money($productCost)} product"
            . ($burden > 0 ? " − {$money($burden)} burden" : '')
            . " − {$money($hourly)} hourly − {$money($shipping)} shipments) = {$money($left)} × {$pct}% = {$money($share)}"
            . (! $countsTowardShare ? ' (non-primary on a collab show — the share goes to the primary)' : '')
            . ($countsTowardShare && $left < 0 ? ' (show did not cover its costs, so no share)' : '')
            . ($logEntry ? '' : '. No show report filed, so product cost counted as $0.00');

        return [
            'earnings' => round($hourly + $shipping + $share, 2), // the whole show; paid as a wage line + a share line
            'note' => $note,
            'pwe' => $pwe,
            'packages' => $packages,
            'columns' => [
                'product_cost' => $productCost,
                'hours_worked' => $hours,
                'shipments_count' => $pwe + $packages,
                'burden_amount' => $burden,
                'net_revenue_basis' => $left,
                'hourly_pay' => $hourly,
                'shipment_pay' => $shipping,
                'share' => $share,
            ],
        ];
    }

    /**
     * The Hours Worked payroll pays for one person on one show, or null when
     * there are none: the report's figure (as an admin settled it, if they
     * did), else the time this person clocked against the show.
     */
    public function workedHours(Streamer $streamer, Show $show, ?StreamerLogEntry $logEntry): ?float
    {
        if ($logEntry?->hours_streamed !== null) {
            return round((float) $logEntry->hours_streamed, 2);
        }

        $minutes = app(TimeTrackingService::class)->clockMinutesForShow($show->id, $streamer->user_id);

        return $minutes > 0 ? round($minutes / 60, 2) : null;
    }

    private function defaultOwnerFee(): array
    {
        $type = Setting::get('default_owner_fee_type', '');

        if (! in_array($type, ['percentage', 'flat'], true)) {
            return [null, 0.0, false];
        }

        return [
            $type,
            (float) Setting::get('default_owner_fee_value', 0),
            (bool) Setting::get('default_owner_fee_deduct_from_payout', true),
        ];
    }

    private function resolveRoutingLabel(Streamer $streamer, Show $show): ?string
    {
        $rules = $streamer->channel_routing_rules;

        if (empty($rules) || ! is_array($rules)) {
            return null;
        }

        $channelName = $show->relationLoaded('channel')
            ? $show->channel?->name
            : $show->channel()->value('name');

        if (! $channelName) {
            return null;
        }

        foreach ($rules as $rule) {
            if (isset($rule['channel'], $rule['bank_label'])
                && strcasecmp((string) $rule['channel'], $channelName) === 0) {
                return (string) $rule['bank_label'];
            }
        }

        return null;
    }

    private function evaluateCustomFormula(string $formula, array $variables): float
    {
        if (! preg_match('/^[\w\s+\-*\/().]+$/', $formula)) {
            throw new \RuntimeException('Custom payout formula contains unsupported characters.');
        }

        $tokens = $this->tokenizeFormula($formula);
        $output = [];
        $operators = [];
        $precedence = ['+' => 1, '-' => 1, '*' => 2, '/' => 2];

        foreach ($tokens as $token) {
            if (is_numeric($token)) {
                $output[] = (float) $token;
                continue;
            }

            if (preg_match('/^[A-Za-z_]\w*$/', $token)) {
                if (! array_key_exists($token, $variables)) {
                    throw new \RuntimeException("Unknown formula variable: {$token}");
                }

                $output[] = (float) $variables[$token];
                continue;
            }

            if (isset($precedence[$token])) {
                while (! empty($operators)) {
                    $top = end($operators);
                    if (! isset($precedence[$top]) || $precedence[$top] < $precedence[$token]) {
                        break;
                    }

                    $output[] = array_pop($operators);
                }

                $operators[] = $token;
                continue;
            }

            if ($token === '(') {
                $operators[] = $token;
                continue;
            }

            if ($token === ')') {
                while (! empty($operators) && end($operators) !== '(') {
                    $output[] = array_pop($operators);
                }

                if (empty($operators) || array_pop($operators) !== '(') {
                    throw new \RuntimeException('Custom payout formula has mismatched parentheses.');
                }
            }
        }

        while (! empty($operators)) {
            $operator = array_pop($operators);
            if (in_array($operator, ['(', ')'], true)) {
                throw new \RuntimeException('Custom payout formula has mismatched parentheses.');
            }

            $output[] = $operator;
        }

        return $this->evaluateRpn($output);
    }

    private function tokenizeFormula(string $formula): array
    {
        preg_match_all('/([A-Za-z_]\w*|\d+(?:\.\d+)?|[()+\-*\/])/', str_replace(' ', '', $formula), $matches);

        return $matches[0] ?? [];
    }

    private function evaluateRpn(array $tokens): float
    {
        $stack = [];

        foreach ($tokens as $token) {
            if (is_float($token) || is_int($token)) {
                $stack[] = (float) $token;
                continue;
            }

            $right = array_pop($stack);
            $left = array_pop($stack);

            if ($left === null || $right === null) {
                throw new \RuntimeException('Custom payout formula is incomplete.');
            }

            $stack[] = match ($token) {
                '+' => $left + $right,
                '-' => $left - $right,
                '*' => $left * $right,
                '/' => $right == 0.0 ? throw new \RuntimeException('Custom payout formula cannot divide by zero.') : $left / $right,
                default => throw new \RuntimeException("Unsupported operator in formula: {$token}"),
            };
        }

        if (count($stack) !== 1) {
            throw new \RuntimeException('Custom payout formula could not be evaluated.');
        }

        return (float) $stack[0];
    }

    public function createWeeklyBatch(string $weekStart, ?int $businessId = null): WeeklyPayoutBatch
    {
        [$start, $end] = \App\Support\PaySchedule::periodFor($weekStart, $businessId);

        return DB::transaction(function () use ($start, $end, $businessId) {
            $batch = WeeklyPayoutBatch::create([
                'business_id' => $businessId,
                'week_start' => $start->toDateString(),
                'week_end'   => $end->toDateString(),
                'status'     => 'draft',
                'created_by' => Auth::id(),
            ]);

            Payout::whereNull('weekly_payout_batch_id')
                ->where('status', 'draft')
                ->whereHas('show', fn ($q) => $q->whereBetween('show_date', [$start, $end]))
                ->when($businessId !== null, fn ($q) => $q->whereHas('streamer', fn ($s) => $s->where('business_id', $businessId)))
                ->update(['weekly_payout_batch_id' => $batch->id]);

            $batch->recalculateTotal();

            return $batch;
        });
    }

    /**
     * Create a manual pay run for specific streamers.
     *
     * For each streamer, existing draft show-based payouts in the date range are
     * attached first. If none exist, a $0 manual placeholder payout is created so
     * the amount can be set on the view page.
     *
     * @param array<int> $streamerIds
     */
    public function createManualBatch(string $weekStart, array $streamerIds, ?string $notes = null, ?int $businessId = null): WeeklyPayoutBatch
    {
        [$start, $end] = \App\Support\PaySchedule::periodFor($weekStart, $businessId);

        return DB::transaction(function () use ($start, $end, $streamerIds, $notes, $businessId) {
            $batch = WeeklyPayoutBatch::create([
                'business_id' => $businessId,
                'week_start' => $start->toDateString(),
                'week_end'   => $end->toDateString(),
                'status'     => 'draft',
                'notes'      => $notes,
                'created_by' => Auth::id(),
            ]);

            foreach ($streamerIds as $streamerId) {
                $attached = Payout::where('streamer_id', $streamerId)
                    ->whereNull('weekly_payout_batch_id')
                    ->where('status', 'draft')
                    ->whereHas('show', fn ($q) => $q->whereBetween('show_date', [$start, $end]))
                    ->update(['weekly_payout_batch_id' => $batch->id]);

                if ($attached === 0) {
                    $streamer = Streamer::find($streamerId);
                    if ($streamer) {
                        Payout::create([
                            'streamer_id'            => $streamerId,
                            'weekly_payout_batch_id' => $batch->id,
                            'payout_type'            => $streamer->payout_type ?? 'flat_rate',
                            'calculated_payout'      => 0,
                            'gross_show_revenue'     => 0,
                            'owner_fee_deducted'     => 0,
                            'tips_included'          => 0,
                            'calculation_notes'      => 'Manual entry',
                            'status'                 => 'draft',
                        ]);
                    }
                }
            }

            $batch->recalculateTotal();
            return $batch;
        });
    }

    /**
     * Read-only dry run of what finalizing this batch will pay each streamer,
     * including the loan repayments that would be deducted — without mutating any
     * payout or loan. Mirrors applyLoanRepayments() exactly (deduction applies to
     * a streamer's first payout by id and is capped at that payout's amount) so
     * the preview matches what finalizing will actually do.
     *
     * @return array{rows: array<int, array{streamer: string, gross: float, loan: float, net: float}>, gross: float, loan: float, net: float}
     */
    public function previewFinalization(WeeklyPayoutBatch $batch): array
    {
        $payouts = $batch->payouts()->with('streamer.loans')->orderBy('id')->get();

        $byStreamer = [];   // streamer_id => [streamer, gross, loan]
        $loanApplied = [];  // streamer_id => already computed loan deduction

        foreach ($payouts as $payout) {
            $sid = $payout->streamer_id;

            if (! isset($byStreamer[$sid])) {
                $byStreamer[$sid] = [
                    'streamer' => $payout->streamer?->name ?? 'Unknown streamer',
                    'gross'    => 0.0,
                    'loan'     => 0.0,
                ];
            }

            $byStreamer[$sid]['gross'] += (float) $payout->calculated_payout;

            // Loan repayment is realised against the first payout only (id order),
            // capped at that payout's amount — same as applyLoanRepayments().
            if (! isset($loanApplied[$sid])) {
                $totalDeducted = 0.0;
                foreach (($payout->streamer?->loans ?? collect())->where('status', 'active') as $loan) {
                    if ($loan->deduct_from_payout) {
                        $totalDeducted += min((float) $loan->weekly_repayment, (float) $loan->remaining_balance);
                    }
                }
                $realised = min($totalDeducted, (float) $payout->calculated_payout);
                $byStreamer[$sid]['loan'] = round($realised, 2);
                $loanApplied[$sid] = true;
            }
        }

        $rows = [];
        $gross = $loan = $net = 0.0;

        foreach ($byStreamer as $row) {
            $rowNet = max(0, round($row['gross'] - $row['loan'], 2));
            $rows[] = [
                'streamer' => $row['streamer'],
                'gross'    => round($row['gross'], 2),
                'loan'     => $row['loan'],
                'net'      => $rowNet,
            ];
            $gross += $row['gross'];
            $loan  += $row['loan'];
            $net   += $rowNet;
        }

        usort($rows, fn ($a, $b) => strcmp($a['streamer'], $b['streamer']));

        return [
            'rows'  => $rows,
            'gross' => round($gross, 2),
            'loan'  => round($loan, 2),
            'net'   => round($net, 2),
        ];
    }

    /**
     * What is not signed off yet in the shows this pay run covers.
     *
     * Finalizing approves every payout in the batch and moves the money into
     * each streamer's balance, and it cannot be undone — so it is the last
     * point at which an unapproved report can still be looked at. It used to
     * check only that the batch was a draft, which meant a week could be
     * signed off and paid while its reports were still being argued about.
     *
     * A list rather than a refusal, because whoever runs payroll has to be
     * able to close a week a streamer never filed for. finalizeBatch() blocks
     * on it unless it is told to go ahead anyway.
     *
     * @return array<int,string>
     */
    public function signOffProblems(WeeklyPayoutBatch $batch): array
    {
        $entries = StreamerLogEntry::query()
            ->whereIn('show_id', $batch->payouts()->whereNotNull('show_id')->pluck('show_id')->unique())
            ->with(['show', 'streamer'])
            ->get();

        $showIds = $batch->payouts()->whereNotNull('show_id')->pluck('show_id')->unique();
        $problems = [];

        foreach ($showIds->diff($entries->pluck('show_id')) as $showId) {
            $title = Show::whereKey($showId)->value('title') ?? "Show #{$showId}";
            $problems[] = "{$title} — no show report was ever filed.";
        }

        foreach ($entries as $entry) {
            $title = $entry->show?->title ?? "Show #{$entry->show_id}";

            if ($entry->hasPendingRevisionRequest()) {
                $problems[] = "{$title} — the streamer has asked to reopen this report.";
                continue;
            }

            if ($entry->status !== 'admin_approved') {
                $problems[] = "{$title} — the report is "
                    . (StreamerLogEntry::statusLabels()[$entry->status] ?? $entry->status)
                    . ', not approved.';
                continue;
            }

            if ($entry->needsFulfillmentReview()) {
                $problems[] = "{$title} — still waiting on fulfillment review.";
            }
        }

        // Hours Worked an admin has not settled: missing, or clock and report disagree.
        $time = app(TimeTrackingService::class);
        foreach ($time->showHoursIssues(null, $showIds->all()) as $issue) {
            $title = $issue['entry']->show?->title ?? "Show #{$issue['entry']->show_id}";
            $problems[] = "{$title} — Hours Worked: " . TimeTrackingService::issueLabel($issue['kind']) . '. Settle it on Time & Attendance.';
        }

        // Clocked time someone edited, added by hand, or left open is not paid until reviewed.
        $userIds = Streamer::whereIn('id', $batch->payouts()->pluck('streamer_id')->unique())->pluck('user_id')->filter();
        $flagged = \App\Models\TimeEntry::needsReview()->whereIn('user_id', $userIds)
            ->whereBetween('clocked_in_at', [$batch->week_start->copy()->startOfDay(), $batch->week_end->copy()->endOfDay()])
            ->count();
        if ($flagged > 0) {
            $problems[] = "{$flagged} time " . \Illuminate\Support\Str::plural('entry', $flagged) . ' in this period ' . ($flagged === 1 ? 'waits' : 'wait') . ' for review on Time & Attendance.';
        }

        return $problems;
    }

    public function finalizeBatch(WeeklyPayoutBatch $batch, bool $force = false): void
    {
        $problems = $this->signOffProblems($batch);

        if ($problems !== [] && ! $force) {
            throw new \RuntimeException(
                'This pay run is not signed off yet: ' . implode(' ', $problems),
            );
        }

        DB::transaction(function () use ($batch, $problems, $force) {
            $locked = WeeklyPayoutBatch::lockForUpdate()->findOrFail($batch->id);
            if ($locked->status !== 'draft') {
                throw new \RuntimeException('Only draft batches can be finalized.');
            }

            // Recorded on the batch rather than only in the activity log: a
            // week closed over open reports is the one somebody asks about
            // months later, and the answer should be on the pay run itself.
            if ($problems !== [] && $force) {
                $batch->notes = trim(($batch->notes ? $batch->notes . "\n" : '')
                    . 'Finalized with ' . count($problems) . ' report(s) not signed off: '
                    . implode(' ', $problems));
            }

            $this->applyLoanRepayments($batch);

            $batch->recalculateTotal();
            $batch->update([
                'status'       => 'finalized',
                'finalized_by' => Auth::id(),
                'finalized_at' => now(),
            ]);

            $batch->payouts()->update(['status' => 'approved']);

            $this->updateStreamerBalances($batch);
        });
    }

    public function markBatchPaid(WeeklyPayoutBatch $batch): void
    {
        DB::transaction(function () use ($batch) {
            $locked = WeeklyPayoutBatch::lockForUpdate()->findOrFail($batch->id);
            if ($locked->status === 'paid') {
                return;
            }

            $batch->update(['status' => 'paid']);
            $batch->payouts()->where('status', '!=', 'paid')->with('streamer')->get()
                ->each(function (Payout $payout) {
                    $payout->update(['status' => 'paid']);
                    Streamer::where('id', $payout->streamer_id)
                        ->increment('total_earnings_paid', (float) $payout->calculated_payout);
                });
        });
    }

    /**
     * Increment each streamer's total_earnings_due by their finalized payout amount.
     * Call after payouts are approved so the running balance stays accurate.
     */
    private function updateStreamerBalances(WeeklyPayoutBatch $batch): void
    {
        $batch->payouts()
            ->where('status', 'approved')
            ->with('streamer')
            ->get()
            ->groupBy('streamer_id')
            ->each(function ($payouts, $streamerId) {
                $total = $payouts->sum('calculated_payout');
                \App\Models\Streamer::where('id', $streamerId)
                    ->increment('total_earnings_due', $total);
            });
    }

    /**
     * Call when a payout is marked as paid to keep total_earnings_paid in sync.
     */
    public function markPayoutPaid(\App\Models\Payout $payout): void
    {
        DB::transaction(function () use ($payout) {
            // Re-read under a lock and stop if it is already paid. This runs
            // off a button; a second press used to increment the streamer's
            // paid total again, and outstanding is (due − paid), so the second
            // press wrote off money that was never sent. markBatchPaid() has
            // always filtered on status for the same reason.
            $locked = \App\Models\Payout::lockForUpdate()->find($payout->getKey());

            if (! $locked || $locked->status === 'paid') {
                return;
            }

            $locked->update(['status' => 'paid']);

            \App\Models\Streamer::where('id', $locked->streamer_id)
                ->increment('total_earnings_paid', (float) $locked->calculated_payout);
        });
    }

    private function applyLoanRepayments(WeeklyPayoutBatch $batch): void
    {
        $processedStreamers = [];

        $payouts = $batch->payouts()
            ->with('streamer.loans')
            ->orderBy('id')
            ->get();

        foreach ($payouts as $payout) {
            if (in_array($payout->streamer_id, $processedStreamers)) {
                continue;
            }

            $activeLoans = $payout->streamer->loans
                ->where('status', 'active');

            // What is actually there to withhold. Repaying more than the
            // payout is not possible, and a balance written down by more than
            // was collected is debt the business quietly forgives.
            $available     = max(0.0, (float) $payout->calculated_payout);
            $totalDeducted = 0.0;
            $loanNotes     = [];

            foreach ($activeLoans as $loan) {
                // "Auto-deduct from payout" off means this loan is repaid some
                // other way. The pay run took nothing for it and must not
                // write its balance down either.
                if (! $loan->deduct_from_payout) {
                    continue;
                }

                if ($available <= 0) {
                    break;
                }

                $repayment = round(min(
                    (float) $loan->weekly_repayment,
                    (float) $loan->remaining_balance,
                    $available,
                ), 2);

                if ($repayment <= 0) {
                    continue;
                }

                $newBalance = max(0, round((float) $loan->remaining_balance - $repayment, 2));

                $loan->update([
                    'remaining_balance' => $newBalance,
                    'status'            => $newBalance <= 0 ? 'paid_off' : 'active',
                ]);

                $available     -= $repayment;
                $totalDeducted += $repayment;
                $loanNotes[]    = '$' . number_format($repayment, 2) . ' ' . $loan->label;
            }

            if ($totalDeducted > 0) {
                $newPayout = max(0, round((float) $payout->calculated_payout - $totalDeducted, 2));
                $notes     = $payout->calculation_notes . ' − $' . number_format($totalDeducted, 2) . ' loan (' . implode(', ', $loanNotes) . ')';

                $payout->update([
                    'loan_repayment_deducted' => $totalDeducted,
                    'calculated_payout'       => $newPayout,
                    'calculation_notes'       => $notes,
                ]);
            }

            $processedStreamers[] = $payout->streamer_id;
        }
    }
}
