<?php

namespace App\Services;

use App\Models\Payout;
use App\Models\Streamer;
use App\Models\WeeklyPayoutBatch;

class AssistantPayoutService
{
    public function sync(WeeklyPayoutBatch $batch): int
    {
        if ($batch->fresh()->status !== 'draft') {
            throw new \RuntimeException('Only draft pay runs can be synced.');
        }

        $rows = app(AssistantPayrollSummary::class)->hours(
            $batch->week_start->toDateString(),
            $batch->week_end->toDateString()
        );
        $count = 0;

        foreach ($rows as $row) {
            $profile = Streamer::query()->where('user_id', $row['user_id'])
                ->when($batch->business_id !== null, fn ($q) => $q->where('business_id', $batch->business_id))
                ->first();

            if (! $profile || $row['hourly_rate'] === null || $row['hours'] <= 0) {
                continue;
            }

            $marker = '[ASSISTANT_HOURS:'.$row['user_id'].']';
            $existing = $batch->payouts()->where('streamer_id', $profile->id)
                ->where('calculation_notes', 'like', '%'.$marker.'%')->first();

            if ($existing) {
                continue;
            }

            Payout::create([
                'streamer_id' => $profile->id,
                'weekly_payout_batch_id' => $batch->id,
                'payout_type' => 'hourly',
                'hours_worked' => $row['hours'],
                'calculated_payout' => $row['estimated_pay'],
                'gross_show_revenue' => 0,
                'owner_fee_deducted' => 0,
                'tips_included' => 0,
                'status' => 'draft',
                'calculation_notes' => $marker.' Assistant time only.',
            ]);
            $count++;
        }

        $batch->recalculateTotal();
        return $count;
    }
}
