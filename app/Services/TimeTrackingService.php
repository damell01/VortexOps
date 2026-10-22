<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\Show;
use App\Models\StreamerLogEntry;
use App\Models\TimeEntry;
use App\Models\TimeEntryChange;
use App\Models\User;
use App\Notifications\VortexAlert;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Hours Worked — clocking, corrections, flags and admin review.
 *
 * Payroll pays approved Hours Worked only, never a show's Whatnot length:
 *  - a show's hours are its report's Hours Worked, compared (not added) with
 *    the time clocked against that show; a gap over 15 minutes, or report
 *    hours with nothing clocked, waits for an admin to pick the right figure;
 *  - time with a reason instead of a show (prep, cleanup...) is paid hourly
 *    to in-house streamers;
 *  - an entry the person edited or added by hand, left open past 12 hours,
 *    or overlapping another is flagged and pays nothing until approved.
 */
class TimeTrackingService
{
    /** Report and clock may differ by this much before an admin has to look. */
    public const MISMATCH_MINUTES = 15;

    /**
     * Report hours with nothing clocked are taken as written. Only a real
     * outlier goes to review: more than double the person's usual, or an hour
     * past the most they've ever reported. Someone with fewer than 3 earlier
     * reports has no usual yet, so only more than 12 hours counts.
     */
    public const OUTLIER_TYPICAL_FACTOR = 2;
    public const OUTLIER_OVER_HIGHEST_MINUTES = 60;
    public const OUTLIER_MIN_HISTORY = 3;
    public const OUTLIER_NO_HISTORY_MINUTES = 12 * 60;

    public const SINCE_SETTING = 'hours_tracking_since';

    /** Shows before this date predate Hours Worked tracking and are not reviewed. */
    public static function since(): Carbon
    {
        try {
            return Carbon::parse((string) Setting::get(self::SINCE_SETTING, '2026-10-14'))->startOfDay();
        } catch (\Throwable) {
            return Carbon::parse('2026-10-14');
        }
    }

    // ── Clock ───────────────────────────────────────────────────────────────

    public function openEntryFor(User $user): ?TimeEntry
    {
        return TimeEntry::where('user_id', $user->id)->whereNull('clocked_out_at')->latest('clocked_in_at')->first();
    }

    public function clockIn(User $user, ?int $showId = null, ?string $reason = null, ?string $note = null): TimeEntry
    {
        return DB::transaction(function () use ($user, $showId, $reason, $note) {
            User::whereKey($user->id)->lockForUpdate()->first();
            if ($this->openEntryFor($user)) {
                throw ValidationException::withMessages(['clock' => 'You are already clocked in.']);
            }
            [$showId, $reason] = $this->purpose($user, $showId, $reason);

            $entry = TimeEntry::create([
                'user_id' => $user->id,
                'show_id' => $showId,
                'reason' => $reason,
                'notes' => $this->clean($note),
                'source' => 'clock',
                'status' => 'ok',
                'clocked_in_at' => now()->startOfMinute(),
            ]);
            $this->log($entry, $user, 'clocked_in', null, $this->snapshot($entry));

            return $entry;
        });
    }

    public function clockOut(User $user): TimeEntry
    {
        return DB::transaction(function () use ($user) {
            $entry = TimeEntry::where('user_id', $user->id)->whereNull('clocked_out_at')->lockForUpdate()->latest('clocked_in_at')->first();
            if (! $entry) {
                throw ValidationException::withMessages(['clock' => 'You are not clocked in.']);
            }
            $entry->clocked_out_at = now()->startOfMinute()->max($entry->clocked_in_at);
            if ($entry->clocked_in_at->diffInHours($entry->clocked_out_at) >= TimeEntry::MISSED_CLOCK_OUT_HOURS) {
                $this->addFlag($entry, 'missed_clock_out');
            }
            $entry->save();
            $this->log($entry, $user, 'clocked_out', null, ['clocked_out_at' => $entry->clocked_out_at->toIso8601String()]);
            $this->checkOverlaps($entry);

            return $entry;
        });
    }

    /** Link time to a show, or give it a reason, after the fact. Not flagged: the minutes do not change. */
    public function link(TimeEntry $entry, User $actor, ?int $showId, ?string $reason, ?string $note = null): TimeEntry
    {
        $this->authorizeEntry($entry, $actor);
        [$showId, $reason] = $this->purpose($entry->user ?? $actor, $showId, $reason, $actor);
        $old = $this->snapshot($entry);
        $entry->fill(['show_id' => $showId, 'reason' => $reason, 'notes' => $this->clean($note) ?? $entry->notes])->save();
        $this->log($entry, $actor, 'linked', $old, $this->snapshot($entry));

        return $entry;
    }

    /**
     * Change an entry's times. The person must say why; their change is
     * logged and flagged for an admin. An admin's change counts as reviewed.
     */
    public function edit(TimeEntry $entry, User $actor, Carbon $in, ?Carbon $out, string $why, ?int $showId = null, ?string $reason = null, ?string $note = null): TimeEntry
    {
        $this->authorizeEntry($entry, $actor);
        // A reason helps the reviewer but is not required; the change is
        // logged and flagged for review either way.
        $why = trim($why);
        $this->validateTimes($in, $out);
        [$showId, $reason] = $this->purpose($entry->user ?? $actor, $showId, $reason, $actor);

        return DB::transaction(function () use ($entry, $actor, $in, $out, $why, $showId, $reason, $note) {
            $old = $this->snapshot($entry);
            $entry->fill([
                'clocked_in_at' => $in->copy()->startOfMinute(),
                'clocked_out_at' => $out?->copy()->startOfMinute(),
                'show_id' => $showId,
                'reason' => $reason,
                'notes' => $this->clean($note) ?? $entry->notes,
                'edited_at' => now(),
            ]);
            if ($this->isReviewer($actor) && $actor->id !== $entry->user_id) {
                $entry->fill(['status' => 'approved', 'approved_minutes' => $entry->duration_minutes, 'approved_by' => $actor->id, 'approved_at' => now(), 'review_note' => $why ?: null]);
            } else {
                $this->addFlag($entry, 'edited');
            }
            $entry->save();
            $this->log($entry, $actor, 'edited', $old, $this->snapshot($entry), $why !== '' ? $why : null);
            if ($entry->clocked_out_at) {
                $this->checkOverlaps($entry);
            }

            return $entry;
        });
    }

    /** Time someone forgot to clock. Added by the person it is flagged; added by an admin it is approved. */
    public function addManual(User $user, User $actor, Carbon $in, Carbon $out, string $why, ?int $showId = null, ?string $reason = null, ?string $note = null): TimeEntry
    {
        if ($actor->id !== $user->id && ! $this->isReviewer($actor)) {
            abort(403);
        }
        $why = trim($why);
        $this->validateTimes($in, $out);
        [$showId, $reason] = $this->purpose($user, $showId, $reason, $actor);

        return DB::transaction(function () use ($user, $actor, $in, $out, $why, $showId, $reason, $note) {
            $byAdmin = $actor->id !== $user->id && $this->isReviewer($actor);
            $entry = new TimeEntry([
                'user_id' => $user->id,
                'show_id' => $showId,
                'reason' => $reason,
                'notes' => $this->clean($note),
                'source' => 'manual',
                'status' => 'ok',
                'clocked_in_at' => $in->copy()->startOfMinute(),
                'clocked_out_at' => $out->copy()->startOfMinute(),
            ]);
            if ($byAdmin) {
                $entry->fill(['status' => 'approved', 'approved_minutes' => $entry->duration_minutes, 'approved_by' => $actor->id, 'approved_at' => now(), 'review_note' => $why ?: null]);
            } else {
                $this->addFlag($entry, 'manual');
            }
            $entry->save();
            $this->log($entry, $actor, 'added', null, $this->snapshot($entry), $why !== '' ? $why : null);
            $this->checkOverlaps($entry);

            return $entry;
        });
    }

    /** Admins can remove an entry outright (a mistake, or test data). The removal is logged. */
    public function delete(TimeEntry $entry, User $admin): void
    {
        abort_unless($this->isReviewer($admin), 403);

        DB::transaction(function () use ($entry, $admin) {
            TimeEntryChange::create([
                'time_entry_id' => null,
                'user_id' => $admin->id,
                'action' => 'deleted',
                'old_values' => $this->snapshot($entry) + ['entry_id' => $entry->id, 'person' => $entry->user_id],
                'new_values' => null,
            ]);
            $entry->delete();
        });
    }

    // ── Review ──────────────────────────────────────────────────────────────

    /**
     * Settle a flagged entry: pay it as clocked, at corrected minutes, or not at all.
     *
     * @param  'clock'|'corrected'|'reject'  $decision
     */
    public function review(TimeEntry $entry, User $admin, string $decision, ?int $minutes = null, ?string $note = null): TimeEntry
    {
        abort_unless($this->isReviewer($admin), 403);
        $old = $this->snapshot($entry);
        $note = trim((string) $note);
        if ($decision === 'corrected' && ($minutes === null || $minutes < 0 || $minutes > 24 * 60 || $note === '')) {
            throw ValidationException::withMessages(['minutes' => 'Enter the corrected time (up to 24h) and the reason for it.']);
        }
        if ($entry->clocked_out_at === null) {
            // A forgotten clock-out: the corrected time is how long they
            // actually worked, and closes the entry at that point.
            if ($decision === 'corrected') {
                $entry->clocked_out_at = $entry->clocked_in_at->copy()->addMinutes($minutes);
            } elseif ($decision !== 'reject') {
                throw ValidationException::withMessages(['decision' => 'This entry was never clocked out. Enter the time actually worked as a correction.']);
            } else {
                $entry->clocked_out_at = $entry->clocked_in_at->copy();
            }
        }

        $entry->fill(match ($decision) {
            'clock' => ['status' => 'approved', 'approved_minutes' => $entry->duration_minutes],
            'corrected' => ['status' => 'approved', 'approved_minutes' => $minutes],
            'reject' => ['status' => 'rejected', 'approved_minutes' => 0],
            default => throw ValidationException::withMessages(['decision' => 'Unknown decision.']),
        })->fill(['approved_by' => $admin->id, 'approved_at' => now(), 'review_note' => $note ?: null])->save();
        $this->log($entry, $admin, 'reviewed:'.$decision, $old, $this->snapshot($entry), $note ?: null);

        return $entry;
    }

    /** Minutes clocked against a show by one person; flagged time counts as clocked, rejected time does not. */
    public function clockMinutesForShow(int $showId, ?int $userId): int
    {
        if (! $userId) {
            return 0;
        }

        return (int) TimeEntry::where('show_id', $showId)->where('user_id', $userId)
            ->whereNotNull('clocked_out_at')->where('status', '!=', 'rejected')->get()
            ->sum(fn (TimeEntry $e) => $e->status === 'approved' ? (int) $e->approved_minutes : (int) $e->duration_minutes);
    }

    /**
     * Shows whose Hours Worked an admin has to settle: no hours at all, report
     * and clock disagree, or report hours (with nothing clocked) that are a
     * real outlier for that person. Everyday report hours need no review.
     *
     * @return Collection<int, array{entry:StreamerLogEntry,kind:string,report_minutes:?int,clock_minutes:int,user_id:?int}>
     */
    public function showHoursIssues(?int $userId = null, ?array $showIds = null): Collection
    {
        $entries = StreamerLogEntry::query()
            ->whereNotNull('submitted_at')
            ->whereNull('hours_review')
            ->whereHas('show', fn ($q) => $q->whereDate('show_date', '>=', self::since()->toDateString())->where('status', '!=', 'cancelled'))
            ->when($userId, fn ($q) => $q->whereHas('streamer', fn ($s) => $s->where('user_id', $userId)))
            ->when($showIds !== null, fn ($q) => $q->whereIn('show_id', $showIds ?: [0]))
            ->with(['show:id,title,show_date,start_time,show_duration,whatnot_channel_id', 'streamer:id,name,user_id,streamer_type'])
            ->get();

        $clock = TimeEntry::whereIn('show_id', $entries->pluck('show_id'))->whereNotNull('clocked_out_at')
            ->where('status', '!=', 'rejected')->get()
            ->groupBy(fn (TimeEntry $e) => $e->show_id.':'.$e->user_id)
            ->map(fn ($g) => $g->sum(fn (TimeEntry $e) => $e->status === 'approved' ? (int) $e->approved_minutes : (int) $e->duration_minutes));

        $history = $this->reportHistory($entries->pluck('streamer_id')->filter()->unique()->all());

        return $entries->map(function (StreamerLogEntry $entry) use ($clock, $history) {
            $uid = $entry->streamer?->user_id;
            $clockMinutes = (int) ($clock[$entry->show_id.':'.$uid] ?? 0);
            $reportMinutes = $entry->hours_streamed !== null ? (int) round((float) $entry->hours_streamed * 60) : null;
            // Their other reports, newest first, not counting this one.
            $earlier = ($history[$entry->streamer_id] ?? collect())->reject(fn ($h) => $h['id'] === $entry->id)->pluck('minutes');
            $reason = $reportMinutes !== null && $clockMinutes === 0 ? $this->outlierReason($reportMinutes, $earlier) : null;
            $kind = match (true) {
                $reportMinutes === null => 'missing',
                $clockMinutes === 0 => $reason ? 'outlier' : null,
                abs($reportMinutes - $clockMinutes) > self::MISMATCH_MINUTES => 'mismatch',
                default => null,
            };

            return $kind ? ['entry' => $entry, 'kind' => $kind, 'reason' => $reason, 'report_minutes' => $reportMinutes, 'clock_minutes' => $clockMinutes, 'user_id' => $uid] : null;
        })->filter()->values();
    }

    /**
     * Why report hours are an outlier for this person, or null when they're
     * ordinary for them. $earlier: their other reports' minutes, newest first.
     */
    public function outlierReason(int $reportMinutes, \Illuminate\Support\Collection $earlier): ?string
    {
        $hours = fn (int $m) => rtrim(rtrim(number_format($m / 60, 1), '0'), '.').'h';
        if ($earlier->count() < self::OUTLIER_MIN_HISTORY) {
            return $reportMinutes > self::OUTLIER_NO_HISTORY_MINUTES ? 'More than '.$hours(self::OUTLIER_NO_HISTORY_MINUTES).' for one show' : null;
        }
        $usual = (int) $earlier->take(10)->median();
        if ($usual > 0 && $reportMinutes > $usual * self::OUTLIER_TYPICAL_FACTOR) {
            return $hours($reportMinutes).' is more than double their usual '.$hours($usual);
        }
        $highest = (int) $earlier->max();
        if ($reportMinutes > $highest + self::OUTLIER_OVER_HIGHEST_MINUTES) {
            return $hours($reportMinutes).' is well past their highest so far ('.$hours($highest).')';
        }

        return null;
    }

    /**
     * Each person's report hours, newest first, for working out their usual
     * and their highest.
     *
     * @return array<int, \Illuminate\Support\Collection<int, array{id:int, minutes:int}>> streamer id => reports
     */
    private function reportHistory(array $streamerIds): array
    {
        if ($streamerIds === []) {
            return [];
        }

        return StreamerLogEntry::whereIn('streamer_id', $streamerIds)->whereNotNull('submitted_at')->where('hours_streamed', '>', 0)
            ->latest('submitted_at')->latest('id')->get(['id', 'streamer_id', 'hours_streamed'])
            ->groupBy('streamer_id')
            ->map(fn ($g) => $g->map(fn ($r) => ['id' => $r->id, 'minutes' => (int) round((float) $r->hours_streamed * 60)])->values())
            ->all();
    }

    public static function issueLabel(string $kind): string
    {
        return [
            'missing' => 'Hours missing',
            'outlier' => 'Unusual report hours',
            'mismatch' => 'Clock and report differ',
        ][$kind] ?? $kind;
    }

    /**
     * Settle a show's Hours Worked: the clocked time, the report's figure, or
     * a corrected one. Writes the paid figure onto the report.
     *
     * @param  'clock'|'report'|'corrected'  $choice
     */
    public function resolveShowHours(StreamerLogEntry $entry, User $admin, string $choice, ?float $hours = null, ?string $note = null): StreamerLogEntry
    {
        abort_unless($this->isReviewer($admin), 403);
        $note = trim((string) $note);
        $entry->loadMissing('streamer:id,user_id');
        $clockMinutes = $this->clockMinutesForShow($entry->show_id, $entry->streamer?->user_id);

        $paid = match ($choice) {
            'clock' => $clockMinutes > 0 ? round($clockMinutes / 60, 2) : throw ValidationException::withMessages(['choice' => 'Nothing was clocked for this show.']),
            'report' => $entry->hours_streamed !== null ? (float) $entry->hours_streamed : throw ValidationException::withMessages(['choice' => 'The report has no hours.']),
            'corrected' => ($hours !== null && $hours >= 0 && $hours <= 24 && $note !== '') ? round($hours, 2) : throw ValidationException::withMessages(['hours' => 'Enter the corrected hours (0–24) and the reason for them.']),
            default => throw ValidationException::withMessages(['choice' => 'Unknown choice.']),
        };

        $old = ['hours_worked' => $entry->hours_streamed !== null ? (float) $entry->hours_streamed : null, 'clock_minutes' => $clockMinutes];
        $entry->forceFill([
            'hours_streamed' => $paid,
            'hours_review' => $choice,
            'hours_reviewed_by' => $admin->id,
            'hours_reviewed_at' => now(),
            'hours_review_note' => $note ?: null,
        ])->save();

        TimeEntryChange::create([
            'streamer_log_entry_id' => $entry->id,
            'user_id' => $admin->id,
            'action' => 'hours_reviewed:'.$choice,
            'old_values' => $old,
            'new_values' => ['hours_worked' => $paid],
            'reason' => $note ?: null,
        ]);

        return $entry;
    }

    // ── Missed clock-outs ───────────────────────────────────────────────────

    /** Flag entries left open past 12 hours and tell the person (and admins) once. */
    public function flagMissedClockOuts(): int
    {
        $count = 0;
        TimeEntry::whereNull('clocked_out_at')->whereNull('reminder_sent_at')
            ->where('clocked_in_at', '<=', now()->subHours(TimeEntry::MISSED_CLOCK_OUT_HOURS))
            ->with('user')->get()
            ->each(function (TimeEntry $entry) use (&$count) {
                $this->addFlag($entry, 'missed_clock_out');
                $entry->reminder_sent_at = now();
                $entry->save();
                $count++;

                $since = $entry->clocked_in_at->copy()->setTimezone($entry->user?->timezone ?: \App\Support\BusinessTime::zone())->format('D M j, g:i A');
                Notifier::send('missed_clock_out', new VortexAlert(
                    event: 'missed_clock_out',
                    title: 'Still clocked in?',
                    body: ($entry->user?->name ?? 'Someone')." has been clocked in since {$since}. Clock out and correct the time — an admin will review it.",
                    tone: 'warning',
                    links: ['Open Timekeeping' => \App\Filament\Pages\Timekeeping::getUrl()],
                ), involved: [$entry->user]);
            });

        return $count;
    }

    // ── Summaries ───────────────────────────────────────────────────────────

    /**
     * One person's hours for a period: clocked, reported on shows, and approved
     * (what payroll pays), plus each day's entries.
     *
     * @return array{clock:int,report:int,approved:int,flagged:int,days:array<string,array{clock:int,entries:Collection}>}
     */
    public function personSummary(User $user, Carbon $from, Carbon $to): array
    {
        $entries = TimeEntry::where('user_id', $user->id)
            ->whereBetween('clocked_in_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->with('show:id,title')->orderBy('clocked_in_at')->get();

        $reports = StreamerLogEntry::query()
            ->whereHas('streamer', fn ($q) => $q->where('user_id', $user->id))
            ->whereHas('show', fn ($q) => $q->whereBetween('show_date', [$from->toDateString(), $to->copy()->endOfDay()->toDateTimeString()]))
            ->get(['id', 'show_id', 'hours_streamed', 'hours_review']);

        $linkedShowIds = $reports->pluck('show_id')->all();
        $approvedClockOnly = $entries->filter(fn (TimeEntry $e) => ! in_array($e->show_id, $linkedShowIds, true))->sum(fn (TimeEntry $e) => $e->payableMinutes());
        $reportMinutes = (int) round($reports->sum(fn ($r) => (float) $r->hours_streamed) * 60);

        $tz = $user->timezone ?: \App\Support\BusinessTime::zone();

        return [
            'clock' => (int) $entries->sum(fn (TimeEntry $e) => (int) ($e->duration_minutes ?? 0)),
            'report' => $reportMinutes,
            'approved' => (int) ($approvedClockOnly + $reportMinutes),
            'flagged' => $entries->where('status', 'flagged')->count(),
            'days' => $entries->groupBy(fn (TimeEntry $e) => $e->clocked_in_at->copy()->setTimezone($tz)->toDateString())
                ->map(fn ($g) => ['clock' => (int) $g->sum(fn ($e) => (int) ($e->duration_minutes ?? 0)), 'entries' => $g])
                ->all(),
        ];
    }

    /**
     * Shows a person can link time to, newest first: the ones they are on
     * (as streamer or fulfillment) from the last two weeks and all upcoming
     * assigned shows. With $all (admins only), other shows are offered too.
     */
    public function linkableShows(User $user, ?int $include = null, bool $all = false): Collection
    {
        $streamerId = $user->streamer?->id;
        $all = $all && ($this->isReviewer($user) || $user->hasRole('streamer_assistant'));

        $shows = Show::query()
            ->where('status', '!=', 'cancelled')
            ->whereDate('show_date', '>=', \App\Support\BusinessTime::local()->subDays(14)->toDateString())
            ->when(! $all, fn ($q) => $q->where(fn ($w) => $w
                ->when($streamerId, fn ($s) => $s->whereHas('streamers', fn ($x) => $x->where('streamers.id', $streamerId)))
                ->orWhereHas('fulfillmentUsers', fn ($x) => $x->where('users.id', $user->id))))
            ->with('channel:id,name')
            ->orderByDesc('show_date')->orderByDesc('start_time')
            ->when($all, fn ($q) => $q->limit(300))
            ->get(['id', 'title', 'show_date', 'start_time', 'whatnot_channel_id']);

        if ($include && ! $shows->contains('id', $include) && ($extra = Show::with('channel:id,name')->find($include, ['id', 'title', 'show_date', 'start_time', 'whatnot_channel_id']))) {
            $shows->push($extra);
        }

        return $shows;
    }

    /**
     * Shows for the clock's show picker, grouped by day: the person's own,
     * and for admins every other show in the window too.
     *
     * @return list<array{id:int,title:string,time:?string,day:string,day_key:string,channel:?string,mine:bool}>
     */
    public function pickerShows(User $user, ?int $include = null): array
    {
        $mine = $this->linkableShows($user, $include)->pluck('id')->all();
        $shows = ($this->isReviewer($user) || $user->hasRole('streamer_assistant')) ? $this->linkableShows($user, $include, true) : $this->linkableShows($user, $include);
        $today = \App\Support\BusinessTime::local()->toDateString();

        // Today first, then what is coming up soonest, then earlier shows newest first.
        $rank = function (Show $s) use ($today) {
            $date = $s->show_date?->toDateString() ?? '0000-00-00';
            $time = $s->start_time?->format('H:i') ?? '00:00';
            if ($date === $today) {
                return '0'.$time;
            }

            return $date > $today ? '1'.$date.$time : '2'.(99999999 - (int) str_replace('-', '', $date)).str_pad((string) (2400 - (int) str_replace(':', '', $time)), 4, '0', STR_PAD_LEFT);
        };

        return $shows->sortBy($rank)
            ->map(function (Show $s) use ($mine, $today) {
                $date = $s->show_date?->toDateString();
                $diff = $date ? (int) Carbon::parse($today)->diffInDays(Carbon::parse($date), false) : null;

                return [
                    'id' => $s->id,
                    'title' => $s->title ?: 'Show #'.$s->id,
                    'time' => $s->start_time?->format('g:i A'),
                    'day_key' => $date ?? '',
                    'day' => match (true) {
                        $diff === 0 => 'Today',
                        $diff === -1 => 'Yesterday',
                        $diff === 1 => 'Tomorrow',
                        default => $s->show_date?->format('l, M j') ?? 'No date',
                    },
                    'channel' => $s->channel?->name,
                    'mine' => in_array($s->id, $mine, true),
                ];
            })->values()->all();
    }

    public function isReviewer(?User $user): bool
    {
        return (bool) ($user?->isAdmin() || $user?->isOwner());
    }

    // ── Internals ───────────────────────────────────────────────────────────

    /** @return array{0:?int,1:?string} */
    private function purpose(User $user, ?int $showId, ?string $reason, ?User $actor = null): array
    {
        $reason = $reason !== null && $reason !== '' ? $reason : null;
        if ($reason !== null && ! array_key_exists($reason, TimeEntry::REASONS)) {
            throw ValidationException::withMessages(['reason' => 'Pick a reason from the list.']);
        }
        if ($showId) {
            $allowed = ($this->isReviewer($actor ?? $user) || ($actor ?? $user)->hasRole('streamer_assistant'))
                ? Show::whereKey($showId)->exists()
                : $this->linkableShows($user)->contains('id', $showId) || TimeEntry::where('user_id', $user->id)->where('show_id', $showId)->exists();
            if (! $allowed) {
                throw ValidationException::withMessages(['show' => 'Pick one of your shows.']);
            }

            return [$showId, null];
        }

        return [null, $reason];
    }

    private function validateTimes(Carbon $in, ?Carbon $out): void
    {
        if ($in->isAfter(now()->addMinutes(5))) {
            throw ValidationException::withMessages(['in' => 'The start time is in the future.']);
        }
        if ($out && ! $out->isAfter($in)) {
            throw ValidationException::withMessages(['out' => 'The end time must be after the start time.']);
        }
        if ($out && $in->diffInHours($out) > 24) {
            throw ValidationException::withMessages(['out' => 'One entry cannot run longer than 24 hours.']);
        }
    }

    private function authorizeEntry(TimeEntry $entry, User $actor): void
    {
        abort_unless($entry->user_id === $actor->id || $this->isReviewer($actor), 403);
        if ($entry->status === 'approved' && $entry->user_id === $actor->id && ! $this->isReviewer($actor)) {
            throw ValidationException::withMessages(['entry' => 'An admin already approved this entry. Ask them to change it.']);
        }
    }

    private function addFlag(TimeEntry $entry, string $flag): void
    {
        $entry->flags = array_values(array_unique([...($entry->flags ?? []), $flag]));
        $entry->status = 'flagged';
        $entry->approved_minutes = null;
        $entry->approved_by = null;
        $entry->approved_at = null;
    }

    private function checkOverlaps(TimeEntry $entry): void
    {
        $end = $entry->clocked_out_at ?? now();
        $others = TimeEntry::where('user_id', $entry->user_id)->whereKeyNot($entry->id)
            ->where('status', '!=', 'rejected')
            ->where('clocked_in_at', '<', $end)
            ->where(fn ($q) => $q->whereNull('clocked_out_at')->orWhere('clocked_out_at', '>', $entry->clocked_in_at))
            ->get();
        if ($others->isEmpty()) {
            return;
        }
        foreach ([$entry, ...$others] as $e) {
            if (! $e->hasFlag('overlap')) {
                $this->addFlag($e, 'overlap');
                $e->save();
            }
        }
    }

    private function snapshot(TimeEntry $entry): array
    {
        return [
            'clocked_in_at' => $entry->clocked_in_at?->toIso8601String(),
            'clocked_out_at' => $entry->clocked_out_at?->toIso8601String(),
            'minutes' => $entry->duration_minutes,
            'show_id' => $entry->show_id,
            'reason' => $entry->reason,
            'status' => $entry->status,
            'approved_minutes' => $entry->approved_minutes,
        ];
    }

    private function log(TimeEntry $entry, ?User $actor, string $action, ?array $old, ?array $new, ?string $reason = null): void
    {
        TimeEntryChange::create([
            'time_entry_id' => $entry->id,
            'user_id' => $actor?->id,
            'action' => $action,
            'old_values' => $old,
            'new_values' => $new,
            'reason' => $reason,
        ]);
    }

    private function clean(?string $note): ?string
    {
        $note = trim((string) $note);

        return $note === '' ? null : mb_substr($note, 0, 255);
    }
}
