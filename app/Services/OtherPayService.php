<?php

namespace App\Services;

use App\Models\Payout;
use App\Models\WeeklyPayoutBatch;
use Illuminate\Support\Facades\DB;

/** Manual extras belong to the run, independently of recalculated wages. */
class OtherPayService
{
    public const TYPE = 'run_adjustment';

    public function add(Payout $source, float $amount, string $description): Payout
    {
        return Payout::create([
            'weekly_payout_batch_id' => $source->weekly_payout_batch_id,
            'streamer_id' => $source->streamer_id,
            'show_id' => $source->show_id,
            'whatnot_channel_id' => $source->whatnot_channel_id,
            'payout_type' => self::TYPE,
            'calculated_payout' => round($amount, 2),
            'calculation_notes' => 'Other Pay: '.$description,
            'status' => 'draft',
        ]);
    }

    /** Separate old inline extras before their calculated wage line is replaced. */
    public function preserveLegacy(Payout $source): void
    {
        DB::transaction(function () use ($source) {
            if (! $source->weekly_payout_batch_id || $source->payout_type === self::TYPE) return;
            $run = WeeklyPayoutBatch::lockForUpdate()->find($source->weekly_payout_batch_id);
            if (! $run || $run->status !== 'draft') return;
            $line = Payout::lockForUpdate()->findOrFail($source->id);
            if ($line->status !== 'draft') return;
            $notes = (string) $line->calculation_notes;
            preg_match_all('/^\[OTHER_PAYMENT\] \+\$([0-9,]+\.[0-9]{2}): ([^\r\n]+)(?:\r?\n(?!\[OTHER_PAYMENT\])[^\r\n]*)*/m', $notes, $matches, PREG_SET_ORDER);
            $cents = 0;
            foreach ($matches as $match) {
                $amount = (int) round((float) str_replace(',', '', $match[1]) * 100);
                if ($amount <= 0) continue;
                $this->add($line, $amount / 100, substr($match[0], strpos($match[0], ': ') + 2));
                $cents += $amount;
                $notes = str_replace($match[0], '', $notes);
            }
            if ($cents > 0) {
                $line->update(['calculated_payout' => ((int) round((float) $line->calculated_payout * 100) - $cents) / 100, 'calculation_notes' => trim($notes)]);
                activity('pay_run')->performedOn($line)->withProperties(['pay_run_id' => $run->id, 'amount' => $cents / 100])->log('Existing Other Pay preserved separately');
                $source->refresh();
            }
        });
    }
}
