<?php

namespace App\Services;

use App\Models\Payout;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\WeeklyPayoutBatch;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Carries a "Changed after payout" difference onto the next draft run the
 * person is part of, as an adjustment line with a note. Positive pays them
 * what they are now owed; negative takes back an overpayment.
 */
final class PayChangeSettlement
{
    public const NOTE_PREFIX = 'Pay change settlement';

    /**
     * The draft run the difference goes on: the earliest draft run that already
     * pays this person, else the earliest draft run for their business that
     * pays wages (not a tips-only or share-only run).
     */
    public function targetRun(Streamer $streamer): ?WeeklyPayoutBatch
    {
        $onRun = WeeklyPayoutBatch::query()->where('status', 'draft')
            ->whereHas('payouts', fn ($q) => $q->where('streamer_id', $streamer->id))
            ->orderBy('week_start')->first();
        if ($onRun) return $onRun;

        $business = $streamer->getRawOriginal('business_id');

        return WeeklyPayoutBatch::query()->where('status', 'draft')
            ->sharingPeopleWith($business ? (int) $business : null)
            ->where(fn ($q) => $q->whereNull('kind')->orWhere('kind', 'wages'))
            ->orderBy('week_start')->first();
    }

    /**
     * Settle one show and person: works the difference out again (it may have
     * moved since the page was opened), then adds the line.
     */
    public function settle(int $showId, int $streamerId, ?string $note, ?int $actorId): Payout
    {
        $row = app(PayChangedAfterPayout::class)->rowFor($showId, $streamerId);
        if (! $row || abs((float) $row['difference']) < 0.01) {
            throw ValidationException::withMessages(['settle' => 'This difference is already settled or no longer there.']);
        }
        $streamer = Streamer::query()->findOrFail($streamerId);
        $run = $this->targetRun($streamer);
        if (! $run) {
            throw ValidationException::withMessages(['settle' => "There is no draft pay run for {$streamer->name} yet. Create the next run, then carry this over."]);
        }
        $show = Show::query()->findOrFail($showId);
        $amount = round((float) $row['difference'], 2);
        $when = $show->show_date?->format('M j, Y');
        $text = self::NOTE_PREFIX . ": {$show->title}" . ($when ? " ({$when})" : '')
            . ' — paid $' . number_format((float) $row['paid'], 2) . ' on run #' . $row['run_id'] . ', now works out to $' . number_format((float) $row['now'], 2)
            . (filled($note) ? '. ' . trim((string) $note) : '');

        return DB::transaction(function () use ($run, $streamer, $show, $amount, $text, $row, $note, $actorId) {
            $locked = WeeklyPayoutBatch::query()->lockForUpdate()->findOrFail($run->id);
            if ($locked->status !== 'draft') {
                throw ValidationException::withMessages(['settle' => 'That run is no longer a draft. Try again.']);
            }
            $payout = Payout::create([
                'weekly_payout_batch_id' => $locked->id,
                'streamer_id' => $streamer->id,
                'show_id' => $show->id,
                'whatnot_channel_id' => $show->whatnot_channel_id,
                'payout_type' => OtherPayService::TYPE,
                'calculated_payout' => $amount,
                'calculation_notes' => 'Other Pay: ' . $text,
                'status' => 'draft',
            ]);
            DB::table('pay_change_settlements')->insert([
                'show_id' => $show->id, 'streamer_id' => $streamer->id, 'source_run_id' => $row['run_id'] ?: null,
                'target_run_id' => $locked->id, 'payout_id' => $payout->id, 'paid' => $row['paid'], 'now' => $row['now'], 'amount' => $amount,
                'note' => filled($note) ? trim((string) $note) : null, 'settled_by' => $actorId, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $locked->recalculateTotal();
            activity('pay_run')->causedBy($actorId)->performedOn($locked)
                ->withProperties(['show_id' => $show->id, 'streamer_id' => $streamer->id, 'amount' => $amount, 'payout_id' => $payout->id])
                ->log('Pay change carried onto this run');

            return $payout;
        });
    }
}
