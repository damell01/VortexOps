<?php

namespace App\Services;

use App\Models\Payout;
use App\Models\Setting;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\WeeklyPayoutBatch;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PayoutService
{
    public function calculateForShow(Show $show): array
    {
        $streamers = $show->streamers;

        if ($streamers->isEmpty()) {
            return [];
        }

        $payouts = [];

        $existingPayouts = Payout::where('show_id', $show->id)
            ->whereIn('streamer_id', $streamers->pluck('id'))
            ->get()
            ->keyBy('streamer_id');

        // Prefer the pivot's actual is_primary flag. Shows that predate that
        // flag (or where detectStreamers() never set one) have no streamer
        // flagged primary at all — fall back to treating the first attached
        // streamer as primary so solo-streamer shows and older data keep
        // computing exactly as before.
        $hasExplicitPrimary = $streamers->contains(fn (Streamer $s) => (bool) ($s->pivot->is_primary ?? false));

        foreach ($streamers as $index => $streamer) {
            $existing = $existingPayouts->get($streamer->id);

            $isPrimary = $hasExplicitPrimary
                ? (bool) ($streamer->pivot->is_primary ?? false)
                : $index === 0;

            $result = $this->computeStreamerPayout($streamer, $show, $streamers->count(), $isPrimary);

            $payout = $existing
                ? $existing->fill($result)
                : new Payout(array_merge($result, [
                    'show_id'     => $show->id,
                    'streamer_id' => $streamer->id,
                ]));

            $payout->save();
            $payouts[] = $payout;
        }

        return $payouts;
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

        $hours    = $show->show_duration ? round($show->show_duration / 60, 2) : 0;
        $pweCount   = 0;
        $labelCount = 0;
        $burdenRateApplied = null;

        $logEntry = $show->relationLoaded('streamerLogEntry')
            ? $show->getRelation('streamerLogEntry')
            : $show->streamerLogEntry()->first();

        switch ($streamer->payout_type) {
            case 'profit_share':
                $pct              = (float) $streamer->payout_percentage / 100;
                $calculatedPayout = round($streamerShare * $pct, 2);
                $notPrimaryNote   = $streamerCount > 1 && ! $isPrimary
                    ? ' — non-primary on collab show, revenue share goes to the primary streamer; split manually outside VortexOps'
                    : '';
                if ($streamer->include_tips) {
                    $calculatedPayout += $tipShare;
                    $calculationNotes  = "Profit share {$streamer->payout_percentage}% of \${$streamerShare} + \${$tipShare} tips{$notPrimaryNote}";
                } else {
                    $calculationNotes = "Profit share {$streamer->payout_percentage}% of \${$streamerShare}{$notPrimaryNote}";
                }
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
                $actualHours = $hours > 0 ? $hours : 1;
                if ($hours <= 0) {
                    Log::warning('PayoutService: show_duration is 0 or missing; defaulting to 1 hour for hourly streamer', [
                        'show_id'     => $show->id,
                        'streamer_id' => $streamer->id,
                    ]);
                }
                $calculatedPayout = round((float) $streamer->hourly_rate * $actualHours, 2);
                $durationNote     = $hours > 0 ? "{$actualHours}hrs" : "1hr (show_duration missing)";
                $calculationNotes = "Hourly rate \${$streamer->hourly_rate}/hr × {$durationNote}";
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
                }
                if ($streamer->include_tips) {
                    $calculatedPayout += $tipShare;
                    $calculationNotes .= " + \${$tipShare} tips";
                }
                break;

            case 'hybrid':
                // Hourly base + tips share + profit share component, with optional burden rate.
                $hourlyBase   = $hours > 0 ? round((float) ($streamer->hourly_rate ?? 0) * $hours, 2) : 0;
                $profitComp   = round($streamerShare * ((float) ($streamer->payout_percentage ?? 0) / 100), 2);
                $base         = $hourlyBase + $profitComp + ($streamer->include_tips ? $tipShare : 0);

                if ($streamer->burden_rate_type && (float) ($streamer->burden_rate_value ?? 0) > 0) {
                    $burdenRateApplied = $streamer->burden_rate_type === 'percentage'
                        ? round($base * ((float) $streamer->burden_rate_value / 100), 4)
                        : (float) $streamer->burden_rate_value;
                    $base += $burdenRateApplied;
                }

                $calculatedPayout = round($base, 2);
                $calculationNotes = "Hybrid: \${$hourlyBase} hourly ({$hours}hrs) + {$streamer->payout_percentage}% profit (\${$profitComp})";
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
                        'show_duration_hours'   => $hours,
                        'show_duration_minutes' => (float) ($show->show_duration ?? 0),
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

        return [
            'payout_type'          => $streamer->payout_type,
            'gross_show_revenue'   => $grossRevenue,
            'owner_fee_deducted'   => $ownerFeeDeducted,
            'tips_included'        => $streamer->include_tips ? $tipShare : 0,
            'pwe_count'            => $pweCount > 0 ? $pweCount : null,
            'label_count'          => $labelCount > 0 ? $labelCount : null,
            'burden_rate_applied'  => $burdenRateApplied,
            'calculated_payout'    => $calculatedPayout,
            'calculation_notes'    => $calculationNotes,
            'routing_bank_label'   => $this->resolveRoutingLabel($streamer, $show),
            'status'               => 'draft',
        ];
    }

    /**
     * The global owner-fee fallback, set once in Settings instead of on every
     * streamer. Returns [type, value, deductFromPayout]; type is null when no
     * default is configured.
     *
     * @return array{0: ?string, 1: float, 2: bool}
     */
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

    public function createWeeklyBatch(string $weekStart): WeeklyPayoutBatch
    {
        $start = Carbon::parse($weekStart)->startOfWeek(Carbon::MONDAY);
        $end   = $start->copy()->endOfWeek(Carbon::SUNDAY);

        return DB::transaction(function () use ($start, $end) {
            $batch = WeeklyPayoutBatch::create([
                'week_start' => $start->toDateString(),
                'week_end'   => $end->toDateString(),
                'status'     => 'draft',
                'created_by' => Auth::id(),
            ]);

            Payout::whereNull('weekly_payout_batch_id')
                ->where('status', 'draft')
                ->whereHas('show', fn ($q) => $q->whereBetween('show_date', [$start, $end]))
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
    public function createManualBatch(string $weekStart, array $streamerIds, ?string $notes = null): WeeklyPayoutBatch
    {
        $start = Carbon::parse($weekStart)->startOfWeek(Carbon::MONDAY);
        $end   = $start->copy()->endOfWeek(Carbon::SUNDAY);

        return DB::transaction(function () use ($start, $end, $streamerIds, $notes) {
            $batch = WeeklyPayoutBatch::create([
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

    public function finalizeBatch(WeeklyPayoutBatch $batch): void
    {
        DB::transaction(function () use ($batch) {
            $locked = WeeklyPayoutBatch::lockForUpdate()->findOrFail($batch->id);
            if ($locked->status !== 'draft') {
                throw new \RuntimeException('Only draft batches can be finalized.');
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
            $payout->update(['status' => 'paid']);
            \App\Models\Streamer::where('id', $payout->streamer_id)
                ->increment('total_earnings_paid', (float) $payout->calculated_payout);
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

            $totalDeducted = 0;
            $loanNotes     = [];

            foreach ($activeLoans as $loan) {
                $repayment  = min((float) $loan->weekly_repayment, (float) $loan->remaining_balance);
                $newBalance = max(0, round((float) $loan->remaining_balance - $repayment, 2));

                $loan->update([
                    'remaining_balance' => $newBalance,
                    'status'            => $newBalance <= 0 ? 'paid_off' : 'active',
                ]);

                if ($loan->deduct_from_payout) {
                    $totalDeducted += $repayment;
                    $loanNotes[]    = "\${$repayment} {$loan->label}";
                }
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
