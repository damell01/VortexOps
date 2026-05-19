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

        foreach ($streamers as $streamer) {
            $existing = Payout::where('show_id', $show->id)
                ->where('streamer_id', $streamer->id)
                ->first();

            $result = $this->computeStreamerPayout($streamer, $show, $streamers->count());

            $payout = $existing
                ? $existing->fill($result)
                : new Payout(array_merge($result, [
                    'show_id'    => $show->id,
                    'streamer_id' => $streamer->id,
                ]));

            $payout->save();
            $payouts[] = $payout;
        }

        return $payouts;
    }

    private function computeStreamerPayout(Streamer $streamer, Show $show, int $streamerCount): array
    {
        $netRevenue      = (float) $show->whatnot_net;
        $tips            = (float) $show->tips;
        $streamerShare   = $streamerCount > 1 ? $netRevenue / $streamerCount : $netRevenue;

        $calculatedPayout = 0;
        $calculationNotes = '';

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
                $calculatedPayout = (float) $streamer->package_rate;
                $calculationNotes = "Package rate \${$streamer->package_rate}";
                if ($streamer->include_tips) {
                    $tipShare          = round($tips / $streamerCount, 2);
                    $calculatedPayout += $tipShare;
                    $calculationNotes .= " + \${$tipShare} tips";
                }
                break;

            case 'hourly':
                $hours = $show->show_duration ? round($show->show_duration / 60, 2) : 1;
                $calculatedPayout = round((float) $streamer->hourly_rate * $hours, 2);
                $calculationNotes = "Hourly rate \${$streamer->hourly_rate}/hr × {$hours}hrs";
                break;

            case 'flat_rate':
                $calculatedPayout = (float) ($streamer->package_rate ?? 0);
                $calculationNotes = "Flat rate \${$calculatedPayout}";
                break;
        }

        return [
            'payout_type'        => $streamer->payout_type,
            'gross_show_revenue' => $netRevenue,
            'owner_fee_deducted' => 0,
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

        $batch->recalculateTotal();
        $batch->update([
            'status'       => 'finalized',
            'finalized_by' => Auth::id(),
            'finalized_at' => now(),
        ]);

        $batch->payouts()->update(['status' => 'approved']);
    }
}
