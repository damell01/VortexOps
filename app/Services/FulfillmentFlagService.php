<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class FulfillmentFlagService
{
    public const TYPES = ['missing_item', 'damaged', 'wrong_item', 'wrong_count', 'cancelled', 'other'];

    public function report(User $actor, int $showId, string $type, string $details, ?int $runId = null, ?int $buyerId = null, ?string $orderNumber = null): int
    {
        abort_unless($actor->isOwner() || $actor->isAdmin() || $actor->isFulfillmentAdmin() || $actor->isFulfillment(), 403);
        if (! in_array($type, self::TYPES, true) || trim($details) === '') {
            throw ValidationException::withMessages(['details' => 'Choose a valid issue type and enter details.']);
        }
        if ($runId && ! DB::table('fulfillment_runs')->where('id', $runId)->where('show_id', $showId)->exists()) {
            throw ValidationException::withMessages(['run_id' => 'Run does not belong to this show.']);
        }
        if ($buyerId && (! $runId || ! DB::table('fulfillment_run_buyers')->where('id', $buyerId)->where('run_id', $runId)->exists())) {
            throw ValidationException::withMessages(['buyer_id' => 'Buyer does not belong to this run.']);
        }
        if (! ($actor->isOwner() || $actor->isAdmin() || $actor->isFulfillmentAdmin())) {
            $assigned = DB::table('fulfillment_run_packers')->where('run_id', $runId)->where('user_id', $actor->id)->exists();
            if (! $assigned) abort(403);
        }
        return DB::transaction(function () use ($actor, $showId, $type, $details, $runId, $buyerId, $orderNumber) {
            $id = DB::table('fulfillment_flags')->insertGetId([
                'show_id' => $showId, 'run_id' => $runId, 'buyer_id' => $buyerId,
                'reported_by' => $actor->id, 'type' => $type,
                'details' => trim($details), 'order_number' => $orderNumber,
                'status' => 'open', 'created_at' => now(), 'updated_at' => now(),
            ]);
            if ($buyerId) DB::table('fulfillment_run_buyers')->where('id', $buyerId)->update(['status' => 'held', 'updated_at' => now()]);
            return $id;
        });
    }

    public function resolve(User $actor, int $flagId, string $resolution): void
    {
        abort_unless($actor->isOwner() || $actor->isAdmin() || $actor->isFulfillmentAdmin(), 403);
        if (trim($resolution) === '') throw ValidationException::withMessages(['resolution' => 'Resolution is required.']);
        DB::transaction(function () use ($actor, $flagId, $resolution) {
            $flag = DB::table('fulfillment_flags')->where('id', $flagId)->lockForUpdate()->first();
            abort_unless($flag, 404);
            if ($flag->status === 'resolved') return;
            DB::table('fulfillment_flags')->where('id', $flagId)->update([
                'status' => 'resolved', 'resolution' => trim($resolution),
                'resolved_by' => $actor->id, 'resolved_at' => now(), 'updated_at' => now(),
            ]);
            if ($flag->buyer_id && ! DB::table('fulfillment_flags')
                ->where('buyer_id', $flag->buyer_id)->where('status', 'open')->exists()) {
                DB::table('fulfillment_run_buyers')->where('id', $flag->buyer_id)->where('status', 'held')
                    ->update(['status' => 'waiting', 'updated_at' => now()]);
            }
        });
    }
}
