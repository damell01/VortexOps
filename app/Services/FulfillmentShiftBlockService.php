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
        return DB::table('fulfillment_shift_blocks')->insertGetId([
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
    }

    public function assign(User $actor, int $blockId, int $userId, ?int $stationId = null): void
    {
        $this->manager($actor);
        DB::transaction(function () use ($blockId, $userId, $stationId) {
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
            } else {
                DB::table('fulfillment_shift_assignments')->where('shift_block_id', $blockId)
                    ->where('user_id', $userId)->update(['station_id' => $stationId, 'updated_at' => now()]);
            }
        });
    }

    public function unassign(User $actor, int $blockId, int $userId): void
    {
        $this->manager($actor);
        DB::table('fulfillment_shift_assignments')->where('shift_block_id', $blockId)->where('user_id', $userId)->delete();
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
