<?php

namespace App\Services;

use App\Models\InventoryLocation;
use App\Models\Streamer;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Takes a team profile off payroll.
 *
 * A profile nothing points at yet (no shows, pay, reports, loans or
 * locations) is deleted outright. One with history is archived instead,
 * which sets it inactive so it leaves the payroll roster and new Pay Runs
 * but keeps past shows and pay runs readable under the right name.
 */
class TeamProfileRemoval
{
    /** @return 'deleted'|'archived' */
    public function remove(Streamer $profile): string
    {
        if (! $this->hasHistory($profile)) {
            try {
                DB::transaction(fn () => $profile->delete());

                return 'deleted';
            } catch (QueryException) {
                // Something else references it after all; archive below.
            }
        }

        $profile->update(['status' => 'inactive']);

        return 'archived';
    }

    public function restore(Streamer $profile): void
    {
        $profile->update(['status' => 'active']);
    }

    public function hasHistory(Streamer $profile): bool
    {
        return $profile->shows()->exists()
            || $profile->payouts()->exists()
            || $profile->streamerLogEntries()->exists()
            || $profile->loans()->exists()
            || $profile->profitSharePackets()->exists()
            || InventoryLocation::where('streamer_id', $profile->id)->exists();
    }
}
