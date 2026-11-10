<?php

namespace App\Services;

use App\Models\Show;
use App\Models\User;
use App\Notifications\VortexAlert;
use App\Support\FulfillmentAccess;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The fulfillment schedule. Shifts are stored as full start and end date-times
 * in business time, so a night shift that runs past midnight is one shift.
 */
final class FulfillmentScheduleService
{
    public function create(User $actor, int $userId, string $start, string $end, ?int $stationId = null, ?string $notes = null, array $showIds = [], ?string $kind = null): int
    {
        $this->authorizeManager($actor);
        [$starts, $ends] = $this->window($start, $end);
        $this->validateShift($userId, $stationId);

        $id = DB::transaction(function () use ($actor, $userId, $starts, $ends, $stationId, $notes, $showIds, $kind) {
            $this->assertNoOverlap($userId, $starts, $ends);
            $id = DB::table('fulfillment_shifts')->insertGetId([
                'user_id' => $userId, 'station_id' => $stationId,
                'starts_at' => $starts->toDateTimeString(), 'ends_at' => $ends->toDateTimeString(),
                'kind' => $kind ?: self::kindFor($starts), 'status' => 'scheduled',
                'notes' => $notes ? trim($notes) : null, 'created_by' => $actor->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->syncShows($id, $showIds);

            return $id;
        });

        $this->notify($userId, 'New shift: ' . $starts->format('D M j, g:i A') . ' – ' . $ends->format('g:i A'));

        return $id;
    }

    public function update(User $actor, int $shiftId, int $userId, string $start, string $end, ?int $stationId = null, ?string $notes = null, array $showIds = [], ?string $kind = null): void
    {
        $this->authorizeManager($actor);
        [$starts, $ends] = $this->window($start, $end);
        $this->validateShift($userId, $stationId);
        $previous = DB::table('fulfillment_shifts')->where('id', $shiftId)->value('user_id');
        abort_unless($previous, 404);

        DB::transaction(function () use ($shiftId, $userId, $starts, $ends, $stationId, $notes, $showIds, $kind) {
            $this->assertNoOverlap($userId, $starts, $ends, $shiftId);
            DB::table('fulfillment_shifts')->where('id', $shiftId)->update([
                'user_id' => $userId, 'station_id' => $stationId,
                'starts_at' => $starts->toDateTimeString(), 'ends_at' => $ends->toDateTimeString(),
                'kind' => $kind ?: self::kindFor($starts), 'notes' => $notes ? trim($notes) : null, 'updated_at' => now(),
            ]);
            $this->syncShows($shiftId, $showIds);
        });

        $label = $starts->format('D M j, g:i A') . ' – ' . $ends->format('g:i A');
        $this->notify($userId, 'Shift updated: ' . $label);
        if ((int) $previous !== $userId) $this->notify((int) $previous, 'Shift reassigned: ' . $label . ' is no longer yours.');
    }

    public function delete(User $actor, int $shiftId): void
    {
        $this->authorizeManager($actor);
        $shift = DB::table('fulfillment_shifts')->where('id', $shiftId)->first();
        if (! $shift) return;
        DB::table('fulfillment_shifts')->where('id', $shiftId)->delete();
        $this->notify((int) $shift->user_id, 'Shift removed: ' . CarbonImmutable::parse($shift->starts_at)->format('D M j, g:i A'));
    }

    public function copyWeek(User $actor, string $sourceMonday, string $targetMonday): int
    {
        $this->authorizeManager($actor);
        $source = CarbonImmutable::parse($sourceMonday)->startOfWeek();
        $target = CarbonImmutable::parse($targetMonday)->startOfWeek();
        if ($source->equalTo($target)) return 0;
        $weeks = (int) round($source->startOfDay()->diffInDays($target->startOfDay(), false) / 7);
        $rows = DB::table('fulfillment_shifts')
            ->where('starts_at', '>=', $source->toDateTimeString())
            ->where('starts_at', '<', $source->addWeek()->toDateTimeString())->get();
        $count = 0;
        foreach ($rows as $shift) {
            $showIds = DB::table('fulfillment_shift_shows')->where('shift_id', $shift->id)->pluck('show_id')->all();
            try {
                $this->create($actor, (int) $shift->user_id,
                    CarbonImmutable::parse($shift->starts_at)->addWeeks($weeks)->toDateTimeString(),
                    CarbonImmutable::parse($shift->ends_at)->addWeeks($weeks)->toDateTimeString(),
                    $shift->station_id, $shift->notes, $showIds, $shift->kind ?? null);
                $count++;
            } catch (ValidationException $e) {
                // Copying twice must not duplicate a shift that already exists.
                if (! isset($e->errors()['starts_at'])) throw $e;
            }
        }

        return $count;
    }

    /** Night when the shift starts at 5 PM or later, or before 6 AM. */
    public static function kindFor(CarbonImmutable $start): string
    {
        $hour = $start->setTimezone(\App\Support\BusinessTime::zone())->hour;

        return ($hour >= 17 || $hour < 6) ? 'night' : 'day';
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private function window(string $start, string $end): array
    {
        $starts = CarbonImmutable::parse($start);
        $ends = CarbonImmutable::parse($end);
        // A shift entered as 7 PM – 3 AM ends the next morning.
        if ($ends->lessThanOrEqualTo($starts) && $ends->isSameDay($starts)) {
            $ends = $ends->addDay();
        }
        if ($ends->lessThanOrEqualTo($starts)) {
            throw ValidationException::withMessages(['ends_at' => 'Shift end must be after start.']);
        }
        if ($starts->diffInHours($ends) > 16) {
            throw ValidationException::withMessages(['ends_at' => 'A shift cannot be longer than 16 hours.']);
        }

        return [$starts, $ends];
    }

    private function validateShift(int $userId, ?int $stationId): void
    {
        $user = User::query()->find($userId);
        if (! $user || ! ($user->hasAnyRole(['fulfillment', 'fulfillment_admin']))) {
            throw ValidationException::withMessages(['user_id' => 'Employee must have a fulfillment role.']);
        }
        if ($stationId !== null && ! DB::table('fulfillment_stations')->where('id', $stationId)->where('active', true)->exists()) {
            throw ValidationException::withMessages(['station_id' => 'Station is unavailable.']);
        }
    }

    private function assertNoOverlap(int $userId, CarbonImmutable $starts, CarbonImmutable $ends, ?int $ignoreId = null): void
    {
        $overlap = DB::table('fulfillment_shifts')->where('user_id', $userId)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->where('starts_at', '<', $ends->toDateTimeString())
            ->where('ends_at', '>', $starts->toDateTimeString())->exists();
        if ($overlap) {
            throw ValidationException::withMessages(['starts_at' => 'Employee already has an overlapping shift.']);
        }
    }

    private function syncShows(int $shiftId, array $showIds): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $showIds))));
        $ids = Show::query()->whereIn('id', $ids)->pluck('id')->all();
        DB::table('fulfillment_shift_shows')->where('shift_id', $shiftId)->whereNotIn('show_id', $ids ?: [0])->delete();
        foreach ($ids as $showId) {
            DB::table('fulfillment_shift_shows')->insertOrIgnore(['shift_id' => $shiftId, 'show_id' => $showId, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    private function notify(int $userId, string $title): void
    {
        $user = User::query()->find($userId);
        if (! $user) return;
        Notifier::send('fulfillment_schedule_changed', new VortexAlert(
            event: 'fulfillment_schedule_changed',
            title: $title,
            body: 'Open your schedule to see the details.',
            links: ['My schedule' => \App\Filament\Pages\FulfillmentActivity::getUrl(['tab' => 'schedule'])],
            icon: 'heroicon-o-calendar-days',
        ), [$user]);
    }

    private function authorizeManager(User $actor): void
    {
        abort_unless(FulfillmentAccess::isManager($actor), 403);
    }
}
