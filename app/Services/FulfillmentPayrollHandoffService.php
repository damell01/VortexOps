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
            DB::table('fulfillment_runs')->where('id', $runId)->update([
                'reported_shipments' => $shipments, 'reported_pwes' => $pwes,
                'hard_copy_verified' => $hardCopy, 'verified_by' => $actor->id,
                'verified_at' => now(), 'updated_at' => now(),
            ]);
        });
    }
}
