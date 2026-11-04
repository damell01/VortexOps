<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class FulfillmentScheduleService
{
    public function create(User $actor, int $userId, string $start, string $end, ?int $stationId = null, ?string $notes = null): int
    {
        $this->authorizeManager($actor);
        $starts = CarbonImmutable::parse($start);
        $ends = CarbonImmutable::parse($end);
        if ($ends->lessThanOrEqualTo($starts)) {
            throw ValidationException::withMessages(['ends_at' => 'Shift end must be after start.']);
        }
        if (! User::role(['fulfillment', 'fulfillment_admin'])->whereKey($userId)->exists()) {
            throw ValidationException::withMessages(['user_id' => 'Unknown employee.']);
        }
        if ($stationId !== null && ! DB::table('fulfillment_stations')->where('id', $stationId)->where('active', true)->exists()) {
            throw ValidationException::withMessages(['station_id' => 'Station is unavailable.']);
        }
        return DB::transaction(function () use ($actor, $userId, $starts, $ends, $stationId, $notes) {
            $overlap = DB::table('fulfillment_shifts')->where('user_id', $userId)
                ->where('starts_at', '<', $ends->toDateTimeString())
                ->where('ends_at', '>', $starts->toDateTimeString())->exists();
            if ($overlap) {
                throw ValidationException::withMessages(['starts_at' => 'Employee already has an overlapping shift.']);
            }
            return DB::table('fulfillment_shifts')->insertGetId([
                'user_id' => $userId, 'station_id' => $stationId,
                'starts_at' => $starts->toDateTimeString(), 'ends_at' => $ends->toDateTimeString(),
                'notes' => $notes, 'created_by' => $actor->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        });
    }

    public function copyWeek(User $actor, string $sourceMonday, string $targetMonday): int
    {
        $this->authorizeManager($actor);
        $source = CarbonImmutable::parse($sourceMonday)->startOfWeek();
        $target = CarbonImmutable::parse($targetMonday)->startOfWeek();
        if ($source->equalTo($target)) return 0;
        $rows = DB::table('fulfillment_shifts')
            ->where('starts_at', '>=', $source->toDateTimeString())
            ->where('starts_at', '<', $source->addWeek()->toDateTimeString())->get();
        $count = 0;
        $weeks = (int) round($source->startOfDay()->diffInDays($target->startOfDay(), false) / 7);
        foreach ($rows as $shift) {
            $start = CarbonImmutable::parse($shift->starts_at)->addWeeks($weeks);
            $end = CarbonImmutable::parse($shift->ends_at)->addWeeks((int) ($source->diffInWeeks($target, false)));
            try {
                $this->create($actor, $shift->user_id, $start->toDateTimeString(), $end->toDateTimeString(), $shift->station_id, $shift->notes);
                $count++;
            } catch (ValidationException $e) {
                // Idempotent copy: do not duplicate an existing overlapping shift.
                if (! isset($e->errors()['starts_at'])) throw $e;
            }
        }
        return $count;
    }

    private function authorizeManager(User $actor): void
    {
        abort_unless($actor->isOwner() || $actor->isAdmin() || $actor->isFulfillmentAdmin(), 403);
    }
}
