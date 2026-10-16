<?php

namespace App\Support;

use App\Models\Payout;
use App\Models\Show;
use App\Models\StreamerLogEntry;
use App\Models\User;
use App\Models\WeeklyPayoutBatch;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;

/**
 * The little Pokémon moments when someone gets something done
 * (resources/js/celebrations.js). Small ones play for about a second after
 * an everyday action; milestones (a first report, a record show, payday…)
 * get a couple of seconds. Each person picks all, milestones only, or off.
 *
 * The page carries a small JSON blob (see payload()) so nothing extra is
 * fetched: sprites load only when a celebration actually plays.
 */
class Celebrations
{
    public const LEVELS = ['all' => 'All of them', 'milestones' => 'Milestones only', 'off' => 'Off'];

    public static function level(?User $user): string
    {
        $level = (string) ($user?->celebrations ?? 'all');

        return isset(self::LEVELS[$level]) ? $level : 'all';
    }

    /** What the browser needs: the person's level and the running catch event, if any. */
    public static function payload(?User $user): array
    {
        $event = Season::eventDetails();

        return [
            'level' => self::level($user),
            'milestone' => self::level($user) === 'off' ? null : rescue(fn () => self::payday($user), null, false),
            'event' => $event ? [
                'key' => $event['key'], 'label' => $event['label'], 'emoji' => $event['emoji'],
                'swap' => $event['swap'], 'pool' => $event['pool'],
            ] : null,
        ];
    }

    /**
     * Play a celebration on the page that's open now. $kind is a key in
     * celebrations.js's registry (or a milestone); $extra rides along (a name, an amount…).
     */
    public static function fire(Component $livewire, string $kind, array $extra = []): void
    {
        $livewire->dispatch('vx-fx', ...['kind' => $kind] + $extra);
    }

    /**
     * A report just went in: is it a milestone? Their first ever report wins,
     * then a record show (the best Whatnot net of all their reports, with at
     * least three before it), then one filed within two hours of the show ending.
     */
    public static function forReport(StreamerLogEntry $entry, ?Show $show): ?array
    {
        if (! $entry->streamer_id) {
            return null;
        }
        $filed = StreamerLogEntry::where('streamer_id', $entry->streamer_id)->whereNotNull('submitted_at');
        if ((clone $filed)->count() <= 1) {
            return ['kind' => 'first_report'];
        }
        $net = (float) ($show?->whatnot_net ?? 0);
        if ($show && $net > 0) {
            $before = Show::whereKeyNot($show->id)->whereIn('id', (clone $filed)->whereNotNull('show_id')->select('show_id'));
            if ((clone $before)->count() >= 3 && $net > (float) $before->max('whatnot_net')) {
                return ['kind' => 'record_show', 'amount' => '$' . number_format($net, 2), 'key' => (string) $show->id];
            }
        }
        if ($show?->show_date && ($end = $show->scheduleMinutes()[1]) !== null) {
            $endedAt = \Carbon\CarbonImmutable::parse($show->show_date->toDateString(), BusinessTime::zone())->addMinutes($end);
            $now = now(BusinessTime::zone());
            if ($now->greaterThanOrEqualTo($endedAt) && $now->diffInMinutes($endedAt, true) <= 120) {
                return ['kind' => 'on_time'];
            }
        }

        return null;
    }

    /**
     * Payday: a pay run holding this streamer's pay was marked paid in the
     * last two weeks. The key is the run, so each payday plays once.
     */
    public static function payday(?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        // Remembered for five minutes, so a page load costs nothing extra.
        return Cache::remember("vx-payday:{$user->id}", 300, function () use ($user) {
            $streamerId = $user->streamer()->value('id');
            if (! $streamerId) {
                return null;
            }
            $batch = WeeklyPayoutBatch::where('status', 'paid')->where('updated_at', '>=', now()->subDays(14))
                ->whereHas('payouts', fn ($q) => $q->where('streamer_id', $streamerId))
                ->latest('updated_at')->first(['id']);
            if (! $batch) {
                return null;
            }
            $amount = (float) Payout::where('weekly_payout_batch_id', $batch->id)->where('streamer_id', $streamerId)->sum('calculated_payout');

            return $amount > 0 ? ['kind' => 'payday', 'key' => (string) $batch->id, 'amount' => '$' . number_format($amount, 2)] : null;
        });
    }

    /** Every other pay run for the same dates is paid too. */
    public static function allPaidFor(WeeklyPayoutBatch $batch): bool
    {
        $others = WeeklyPayoutBatch::whereKeyNot($batch->id)
            ->whereDate('week_start', '<=', $batch->week_end)->whereDate('week_end', '>=', $batch->week_start);

        return (clone $others)->exists() && ! $others->where('status', '!=', 'paid')->exists();
    }

    /** Play one on the next page instead, for an action that redirects. */
    public static function flash(string $kind, array $extra = []): void
    {
        session()->flash('vx_fx', ['kind' => $kind] + $extra);
    }
}
