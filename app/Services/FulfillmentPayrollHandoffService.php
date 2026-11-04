<?php
namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class FulfillmentPayrollHandoffService
{
    public function verify(User $actor, int $runId, int $shipments, int $pwes, bool $hardCopy): void
    {
        abort_unless($actor->isAdmin() || $actor->isFulfillmentAdmin(), 403);
        if ($shipments < 0 || $pwes < 0) {
            throw ValidationException::withMessages(['counts' => 'Counts cannot be negative.']);
        }
        DB::transaction(function () use ($actor, $runId, $shipments, $pwes, $hardCopy) {
            $run = DB::table('fulfillment_runs')->where('id', $runId)->lockForUpdate()->first();
            if (! $run || $run->status !== 'completed' || $run->payroll_handoff_at) {
                throw ValidationException::withMessages(['run' => 'Run must be completed and not handed off.']);
            }
            $packedBuyers = DB::table('fulfillment_run_buyers')
                ->where('run_id', $runId)->where('status', 'packed')->count();
            if ($packedBuyers === 0) {
                throw ValidationException::withMessages(['run' => 'No completed buyer packing records were found.']);
            }
            if ($shipments + $pwes < $packedBuyers) {
                throw ValidationException::withMessages(['counts' => 'Shipment and PWE counts must cover all packed buyers.']);
            }
            DB::table('fulfillment_runs')->where('id', $runId)->update([
                'reported_shipments' => $shipments, 'reported_pwes' => $pwes,
                'hard_copy_verified' => $hardCopy, 'verified_by' => $actor->id,
                'verified_at' => now(), 'updated_at' => now(),
            ]);
        });
    }
    public function handoff(User $actor, int $runId, string $method, ?string $notes = null): void
    {
        abort_unless($actor->isAdmin() || $actor->isFulfillmentAdmin(), 403);
        if (! in_array($method, ['single', 'even', 'custom'], true)) {
            throw ValidationException::withMessages(['method' => 'Invalid split method.']);
        }
        DB::transaction(function () use ($actor, $runId, $method, $notes) {
            $run = DB::table('fulfillment_runs')->where('id', $runId)->lockForUpdate()->first();
            if (! $run || ! $run->verified_at || $run->payroll_handoff_at || ! $run->hard_copy_verified) {
                throw ValidationException::withMessages(['run' => 'Manager verification and hard copy are required.']);
            }
            $count = DB::table('fulfillment_run_packers')->where('run_id', $runId)->count();
            if ($count > 1 && $method === 'single') {
                throw ValidationException::withMessages(['method' => 'Select an even or custom split for multiple packers.']);
            }
            if ($method === 'custom' && blank($notes)) {
                throw ValidationException::withMessages(['notes' => 'Describe the agreed split.']);
            }
            DB::table('fulfillment_runs')->where('id', $runId)->update([
                'payroll_split_method' => $method, 'payroll_split_notes' => $notes,
                'payroll_handoff_at' => now(), 'payroll_handoff_by' => $actor->id,
                'updated_at' => now(),
            ]);
        });
    }

}
