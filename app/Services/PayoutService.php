<?php

namespace App\Services;

use App\Models\Payout;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\WeeklyPayoutBatch;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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

        foreach ($streamers as $streamer) {
            $existing = $existingPayouts->get($streamer->id);

            $result = $this->computeStreamerPayout($streamer, $show, $streamers->count());

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

    private function computeStreamerPayout(Streamer $streamer, Show $show, int $streamerCount): array
    {
        $netRevenue    = (float) $show->whatnot_net;
        $grossRevenue  = (float) $show->gross_revenue;
        $tips          = (float) $show->tips;
        $streamerShare = $streamerCount > 1 ? $netRevenue / $streamerCount : $netRevenue;
        $tipShare      = $streamerCount > 0 ? round($tips / $streamerCount, 2) : 0;

        $calculatedPayout = 0;
        $calculationNotes = '';

        switch ($streamer->payout_type) {
            case 'profit_share':
                $pct              = (float) $streamer->payout_percentage / 100;
                $calculatedPayout = round($streamerShare * $pct, 2);
                if ($streamer->include_tips) {
                    $calculatedPayout += $tipShare;
                    $calculationNotes  = "Profit share {$streamer->payout_percentage}% of \${$streamerShare} + \${$tipShare} tips";
                } else {
                    $calculationNotes = "Profit share {$streamer->payout_percentage}% of \${$streamerShare}";
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
                $hours            = $show->show_duration ? round($show->show_duration / 60, 2) : 1;
                $calculatedPayout = round((float) $streamer->hourly_rate * $hours, 2);
                $calculationNotes = "Hourly rate \${$streamer->hourly_rate}/hr × {$hours}hrs";
                break;

            case 'flat_rate':
                $calculatedPayout = (float) ($streamer->package_rate ?? 0);
                $calculationNotes = "Flat rate \${$calculatedPayout}";
                break;

            case 'custom_formula':
                $formula = trim((string) $streamer->custom_payout_formula);
                $calculatedPayout = $formula !== ''
                    ? round($this->evaluateCustomFormula($formula, [
                        'gross_revenue' => $grossRevenue,
                        'whatnot_net' => $netRevenue,
                        'streamer_share_net' => $streamerShare,
                        'units_sold' => (float) $show->units_sold,
                        'show_duration_hours' => $show->show_duration ? round($show->show_duration / 60, 2) : 0,
                        'show_duration_minutes' => (float) ($show->show_duration ?? 0),
                        'tips' => $tips,
                        'tip_share' => $tipShare,
                        'payout_percentage' => (float) ($streamer->payout_percentage ?? 0),
                        'package_rate' => (float) ($streamer->package_rate ?? 0),
                        'hourly_rate' => (float) ($streamer->hourly_rate ?? 0),
                    ]), 2)
                    : 0;
                $calculationNotes = "Custom formula: {$formula}";
                break;
        }

        // Owner fee — calculated against the gross payout before deduction
        $ownerFeeDeducted = 0;
        if ($streamer->owner_fee_type && (float) $streamer->owner_fee_value > 0) {
            $ownerFeeDeducted = $streamer->owner_fee_type === 'percentage'
                ? round($calculatedPayout * ((float) $streamer->owner_fee_value / 100), 2)
                : (float) $streamer->owner_fee_value;

            if ($streamer->owner_fee_deduct_from_payout) {
                $calculatedPayout = max(0, round($calculatedPayout - $ownerFeeDeducted, 2));
                $calculationNotes .= " − \${$ownerFeeDeducted} owner fee";
            }
        }

        return [
            'payout_type'        => $streamer->payout_type,
            'gross_show_revenue' => $netRevenue,
            'owner_fee_deducted' => $ownerFeeDeducted,
            'tips_included'      => $streamer->include_tips ? $tipShare : 0,
            'calculated_payout'  => $calculatedPayout,
            'calculation_notes'  => $calculationNotes,
            'status'             => 'draft',
        ];
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

    public function finalizeBatch(WeeklyPayoutBatch $batch): void
    {
        if ($batch->status !== 'draft') {
            throw new \RuntimeException('Only draft batches can be finalized.');
        }

        // Apply loan repayments — once per streamer, to their first payout in this batch
        $this->applyLoanRepayments($batch);

        $batch->recalculateTotal();
        $batch->update([
            'status'       => 'finalized',
            'finalized_by' => Auth::id(),
            'finalized_at' => now(),
        ]);

        $batch->payouts()->update(['status' => 'approved']);
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
