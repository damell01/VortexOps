<?php

namespace App\Services;

use App\Models\Payout;
use App\Models\ShowFinancial;
use App\Models\Streamer;
use App\Models\WeeklyPayoutBatch;
use App\Models\WhatnotShow;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PayoutService
{
    public function calculateForShow(WhatnotShow $show): array
    {
        $financial = $show->financial;
        $streamers  = $show->streamers;

        if (!$financial || $streamers->isEmpty()) {
            return [];
        }

        $payouts = [];

        foreach ($streamers as $streamer) {
            $existing = Payout::where('whatnot_show_id', $show->id)
                ->where('streamer_id', $streamer->id)
                ->first();

            $result = $this->computeStreamerPayout($streamer, $financial, $streamers->count());

            $payout = $existing
                ? $existing->fill($result)
                : new Payout(array_merge($result, [
                    'whatnot_show_id' => $show->id,
                    'streamer_id'     => $streamer->id,
                ]));

            $payout->save();
            $payouts[] = $payout;
        }

        return $payouts;
    }

    private function computeStreamerPayout(Streamer $streamer, ShowFinancial $financial, int $streamerCount): array
    {
        $netRevenue      = (float) $financial->net_revenue;
        $tips            = (float) $financial->tips_collected;
        $ownerFeePct     = (float) $financial->owner_platform_fee_pct;
        $ownerFee        = round($netRevenue * ($ownerFeePct / 100), 2);
        $revenueAfterFee = $netRevenue - $ownerFee;

        // Split revenue equally among co-streamers for non-profit-share types
        $streamerShare = $streamerCount > 1 ? $revenueAfterFee / $streamerCount : $revenueAfterFee;

        $calculatedPayout  = 0;
        $calculationNotes  = '';

        switch ($streamer->payout_type) {
            case 'profit_share':
                $pct              = (float) $streamer->payout_percentage / 100;
                $calculatedPayout = round($streamerShare * $pct, 2);
                if ($streamer->include_tips) {
                    $tipShare          = round($tips / $streamerCount, 2);
                    $calculatedPayout += $tipShare;
                    $calculationNotes  = "Profit share {$streamer->payout_percentage}% of \${$streamerShare} + \${$tipShare} tips";
                } else {
                    $calculationNotes = "Profit share {$streamer->payout_percentage}% of \${$streamerShare}";
                }
                break;

            case 'package':
                // Package rate: count of break slots sold on this show
                // Caller should pass slot count; we default to 1 here until we have slot tracking
                $calculatedPayout = (float) $streamer->package_rate;
                $calculationNotes = "Package rate \${$streamer->package_rate}";
                if ($streamer->include_tips) {
                    $tipShare          = round($tips / $streamerCount, 2);
                    $calculatedPayout += $tipShare;
                    $calculationNotes .= " + \${$tipShare} tips";
                }
                break;

            case 'hourly':
                // Hours derived from started_at / ended_at — caller fills in
                $calculatedPayout = (float) $streamer->hourly_rate;
                $calculationNotes = "Hourly rate \${$streamer->hourly_rate}/hr (hours TBD)";
                break;

            case 'flat_rate':
                $calculatedPayout = (float) ($streamer->package_rate ?? 0);
                $calculationNotes = "Flat rate \${$calculatedPayout}";
                break;
        }

        return [
            'payout_type'        => $streamer->payout_type,
            'gross_show_revenue' => $netRevenue,
            'owner_fee_deducted' => $ownerFee,
            'tips_included'      => $streamer->include_tips ? round($tips / $streamerCount, 2) : 0,
            'calculated_payout'  => $calculatedPayout,
            'calculation_notes'  => $calculationNotes,
            'status'             => 'draft',
        ];
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

            // Attach all unbatched approved payouts for shows in this week
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
            throw new \RuntimeException("Only draft batches can be finalized.");
        }

        $batch->recalculateTotal();
        $batch->update([
            'status'       => 'finalized',
            'finalized_by' => Auth::id(),
            'finalized_at' => now(),
        ]);

        $batch->payouts()->update(['status' => 'approved']);
    }
}
