<?php

namespace App\Services;

use App\Models\User;
use App\Support\FulfillmentAccess;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Flexible fulfillment planning; does not mutate clock entries, payroll, or
 * the existing per-employee fulfillment_shifts history.
 */
final class FulfillmentShiftBlockService
{
    private function manager(User $actor): void
    {
        abort_unless(FulfillmentAccess::isManager($actor), 403);
    }

    private function record(User $actor, int $blockId, string $action, ?int $employeeId = null, array $details = []): void
    {
        DB::table('fulfillment_shift_block_events')->insert([
            'shift_block_id' => $blockId,
            'actor_id' => $actor->id,
            'employee_id' => $employeeId,
            'action' => $action,
            'details' => json_encode($details),
            'created_at' => now(),
        ]);
    }

    public function create(User $actor, array $data): int
    {
        $this->manager($actor);
        $start = CarbonImmutable::parse($data['starts_at']);
        $end = CarbonImmutable::parse($data['ends_at']);
        if ($end->lessThanOrEqualTo($start)) {
            throw ValidationException::withMessages(['ends_at' => 'Shift end must follow the start.']);
        }
        if ($end->diffInHours($start) > 24) {
            throw ValidationException::withMessages(['ends_at' => 'Shift duration cannot exceed 24 hours.']);
        }
        return DB::transaction(function () use ($actor, $data, $start, $end) {
        $id = DB::table('fulfillment_shift_blocks')->insertGetId([
            'template_id' => $data['template_id'] ?? null,
            'starts_at' => $start->toDateTimeString(),
            'ends_at' => $end->toDateTimeString(),
            'label' => $data['label'] ?? 'Custom Shift',
            'kind' => $data['kind'] ?? 'custom',
            'required_staff' => max(1, min(100, (int) ($data['required_staff'] ?? 1))),
            'station_id' => $data['station_id'] ?? null,
            'show_id' => $data['show_id'] ?? null,
            'notes' => $data['notes'] ?? null,
            'status' => 'scheduled',
            'created_by' => $actor->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->record($actor, $id, 'created');
        return $id;
        });
    }

    public function assign(User $actor, int $blockId, int $userId, ?int $stationId = null): void
    {
        $this->manager($actor);
        DB::transaction(function () use ($actor, $blockId, $userId, $stationId) {
            $block = DB::table('fulfillment_shift_blocks')->where('id', $blockId)->lockForUpdate()->first();
            abort_unless($block, 404);
            if ($block->status === 'cancelled') {
                throw ValidationException::withMessages(['shift' => 'Cannot assign a cancelled shift.']);
            }
            $employee = User::query()->findOrFail($userId);
            if (! FulfillmentAccess::isMember($employee)) {
                throw ValidationException::withMessages(['employee' => 'Select a fulfillment team member.']);
            }
            $overlap = DB::table('fulfillment_shift_assignments as a')
                ->join('fulfillment_shift_blocks as b', 'b.id', '=', 'a.shift_block_id')
                ->where('a.user_id', $userId)->where('b.id', '!=', $blockId)
                ->where('b.status', '!=', 'cancelled')
                ->where('b.starts_at', '<', $block->ends_at)
                ->where('b.ends_at', '>', $block->starts_at)->exists();
            // Include the pre-existing employee-specific schedule during the
            // transition so new blocks cannot double-book legacy shifts.
            $legacyOverlap = DB::table('fulfillment_shifts')
                ->where('user_id', $userId)
                ->where('starts_at', '<', $block->ends_at)
                ->where('ends_at', '>', $block->starts_at)
                ->where('status', '!=', 'cancelled')
                ->exists();
            if ($overlap || $legacyOverlap) {
                throw ValidationException::withMessages(['employee' => 'Employee has an overlapping shift.']);
            }
            $existing = DB::table('fulfillment_shift_assignments')->where('shift_block_id', $blockId)->where('user_id', $userId)->exists();
            if (! $existing) {
                $count = DB::table('fulfillment_shift_assignments')->where('shift_block_id', $blockId)->count();
                if ($count >= $block->required_staff) {
                    throw ValidationException::withMessages(['employee' => 'Shift is fully staffed. Increase required staffing first.']);
                }
                DB::table('fulfillment_shift_assignments')->insert([
                    'shift_block_id' => $blockId, 'user_id' => $userId, 'station_id' => $stationId,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $this->record($actor, $blockId, 'assigned', $userId, ['station_id' => $stationId]);
            } else {
                DB::table('fulfillment_shift_assignments')->where('shift_block_id', $blockId)
                    ->where('user_id', $userId)->update(['station_id' => $stationId, 'updated_at' => now()]);
                $this->record($actor, $blockId, 'station_changed', $userId, ['station_id' => $stationId]);
            }
        });
    }

    /** Duplicate a block without copying staffing or affecting historical shifts. */
    public function duplicate(User $actor, int $blockId, int $days = 7): int
    {
        $this->manager($actor);
        if ($days < 1 || $days > 365) {
            throw ValidationException::withMessages(['date' => 'Choose a date within the next year.']);
        }
        $source = DB::table('fulfillment_shift_blocks')->where('id', $blockId)->first();
        abort_unless($source, 404);
        if ($source->status === 'cancelled') {
            throw ValidationException::withMessages(['shift' => 'Cannot duplicate a cancelled shift.']);
        }
        $start = CarbonImmutable::parse($source->starts_at)->addDays($days);
        $end = CarbonImmutable::parse($source->ends_at)->addDays($days);
        $duplicate = DB::table('fulfillment_shift_blocks')
            ->where('starts_at', $start->toDateTimeString())
            ->where('ends_at', $end->toDateTimeString())
            ->where('kind', $source->kind)
            ->where('label', $source->label)
            ->where('status', '!=', 'cancelled')
            ->exists();
        if ($duplicate) {
            throw ValidationException::withMessages(['shift' => 'A matching shift already exists on that date.']);
        }
        return $this->create($actor, [
            'template_id' => $source->template_id,
            'starts_at' => $start->toDateTimeString(),
            'ends_at' => $end->toDateTimeString(),
            'label' => $source->label,
            'kind' => $source->kind,
            'required_staff' => $source->required_staff,
            'station_id' => $source->station_id,
            'show_id' => null, // Never silently carry an old show to a new week.
            'notes' => $source->notes,
        ]);
    }

    /**
     * Copy a selected week's shift patterns to the next week, without staff
     * assignments or historical show links. Entire batch is transactional.
     */
    public function copyWeek(User $actor, CarbonImmutable $weekStart): int
    {
        $this->manager($actor);
        $weekEnd = $weekStart->addWeek();
        return DB::transaction(function () use ($actor, $weekStart, $weekEnd) {
            $blocks = DB::table('fulfillment_shift_blocks')
                ->where('starts_at', '>=', $weekStart->toDateTimeString())
                ->where('starts_at', '<', $weekEnd->toDateTimeString())
                ->where('status', '!=', 'cancelled')->orderBy('id')->get();
            if ($blocks->count() > 100) {
                throw ValidationException::withMessages(['week' => 'Too many shifts to copy in one batch.']);
            }
            $count = 0;
            foreach ($blocks as $block) {
                $this->duplicate($actor, (int) $block->id);
                $count++;
            }
            return $count;
        });
    }

    public function cancel(User $actor, int $blockId): void
    {
        $this->manager($actor);
        DB::transaction(function () use ($actor, $blockId) {
            $block = DB::table('fulfillment_shift_blocks')->where('id', $blockId)->lockForUpdate()->first();
            abort_unless($block, 404);
            DB::table('fulfillment_shift_blocks')->where('id', $blockId)->update([
                'status' => 'cancelled', 'updated_at' => now(),
            ]);
            $this->record($actor, $blockId, 'cancelled');
            // Preserve assignment history rather than deleting staffing rows.
        });
    }

    public function update(User $actor, int $blockId, array $data): void
    {
        $this->manager($actor);
        $start = CarbonImmutable::parse($data['starts_at']);
        $end = CarbonImmutable::parse($data['ends_at']);
        if ($end->lessThanOrEqualTo($start) || $start->diffInMinutes($end) > 1440) {
            throw ValidationException::withMessages(['ends_at' => 'Shift must last between 1 minute and 24 hours.']);
        }
        DB::transaction(function () use ($actor, $blockId, $data, $start, $end) {
            $block = DB::table('fulfillment_shift_blocks')->where('id', $blockId)->lockForUpdate()->first();
            abort_unless($block, 404);
            if ($block->status === 'cancelled') {
                throw ValidationException::withMessages(['shift' => 'Cancelled shifts cannot be edited.']);
            }
            $count = DB::table('fulfillment_shift_assignments')->where('shift_block_id', $blockId)->count();
            if ((int) $data['required_staff'] < $count) {
                throw ValidationException::withMessages(['required_staff' => 'Cannot reduce staffing below assigned employees.']);
            }
            $assignedIds = DB::table('fulfillment_shift_assignments')->where('shift_block_id', $blockId)->pluck('user_id');
            foreach ($assignedIds as $userId) {
                $overlap = DB::table('fulfillment_shift_assignments as a')
                    ->join('fulfillment_shift_blocks as b', 'b.id', '=', 'a.shift_block_id')
                    ->where('a.user_id', $userId)->where('b.id', '!=', $blockId)
                    ->where('b.status', '!=', 'cancelled')
                    ->where('b.starts_at', '<', $end->toDateTimeString())
                    ->where('b.ends_at', '>', $start->toDateTimeString())->exists();
                $legacy = DB::table('fulfillment_shifts')
                    ->where('user_id', $userId)->where('status', '!=', 'cancelled')
                    ->where('starts_at', '<', $end->toDateTimeString())
                    ->where('ends_at', '>', $start->toDateTimeString())->exists();
                if ($overlap || $legacy) {
                    throw ValidationException::withMessages(['starts_at' => 'New time overlaps an assigned employee schedule.']);
                }
            }
            DB::table('fulfillment_shift_blocks')->where('id', $blockId)->update([
                'starts_at' => $start->toDateTimeString(),
                'ends_at' => $end->toDateTimeString(),
                'kind' => $data['kind'],
                'label' => $data['label'],
                'required_staff' => (int) $data['required_staff'],
                'notes' => $data['notes'] ?? null,
                'updated_at' => now(),
            ]);
            $this->record($actor, $blockId, 'updated', null, ['starts_at' => $start->toDateTimeString(), 'ends_at' => $end->toDateTimeString()]);
        });
    }

    public function unassign(User $actor, int $blockId, int $userId): void
    {
        $this->manager($actor);
        DB::transaction(function () use ($actor, $blockId, $userId) {
            $deleted = DB::table('fulfillment_shift_assignments')->where('shift_block_id', $blockId)->where('user_id', $userId)->delete();
            if ($deleted) $this->record($actor, $blockId, 'unassigned', $userId);
        });
    }

    public function week(User $actor, CarbonImmutable $start)
    {
        abort_unless(FulfillmentAccess::isMember($actor), 403);
        return DB::table('fulfillment_shift_blocks as b')
            ->leftJoin('fulfillment_shift_assignments as a', 'a.shift_block_id', '=', 'b.id')
            ->where('b.starts_at', '>=', $start->toDateTimeString())
            ->where('b.starts_at', '<', $start->addWeek()->toDateTimeString())
            ->when(! FulfillmentAccess::isManager($actor), fn ($q) => $q->where('a.user_id', $actor->id))
            ->select('b.id', 'b.label', 'b.kind', 'b.status', 'b.starts_at', 'b.ends_at', 'b.required_staff',
                'b.station_id', 'b.show_id', DB::raw('COUNT(a.id) as assigned_staff'))
            ->groupBy('b.id', 'b.label', 'b.kind', 'b.status', 'b.starts_at', 'b.ends_at', 'b.required_staff', 'b.station_id', 'b.show_id')
            ->orderBy('b.starts_at')->get();
    }
}
