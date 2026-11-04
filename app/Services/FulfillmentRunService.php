<?php

namespace App\Services;

use App\Models\FulfillmentPackage;
use App\Models\Show;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FulfillmentRunService
{
    public function start(Show $show, int $stationId, User $actor, array $packerIds = []): int
    {
        $manager = $actor->isOwner() || $actor->isAdmin() || $actor->isFulfillmentAdmin();
        if (! $manager && ! $actor->isFulfillment()) {
            abort(403);
        }

        $approved = $show->streamerLogEntry()->where(function ($q) {
            $q->where('status', 'admin_approved')->orWhere('approval_status', 'approved');
        })->whereHas('items')->exists();

        if (! $approved || $show->status === 'cancelled') {
            throw ValidationException::withMessages(['show' => 'The streamer report must be approved before packing.']);
        }

        return DB::transaction(function () use ($show, $stationId, $actor, $packerIds, $manager) {
            $station = DB::table('fulfillment_stations')->where('id', $stationId)->lockForUpdate()->first();
            if (! $station || ! $station->active) {
                throw ValidationException::withMessages(['station' => 'Station is unavailable.']);
            }
            if (DB::table('fulfillment_runs')->where('station_occupied_id', $stationId)->exists()) {
                throw ValidationException::withMessages(['station' => 'Station is already packing another show.']);
            }
            if (DB::table('fulfillment_runs')->where('show_id', $show->id)->where('status', 'packing')->exists()) {
                throw ValidationException::withMessages(['show' => 'Show is already being packed.']);
            }

            $assigned = $show->fulfillmentUsers()->pluck('users.id')->map(fn ($id) => (int) $id)->all();
            $packers = array_values(array_unique(array_map('intval', $packerIds ?: $assigned)));
            if (! $manager) {
                if (! in_array($actor->id, $assigned, true) || array_diff($packers, $assigned)) abort(403);
            } elseif (array_diff($packers, User::query()->whereIn('id', $packers)->pluck('id')->all())) {
                throw ValidationException::withMessages(['packers' => 'Unknown packer.']);
            }
            if (! $packers) {
                throw ValidationException::withMessages(['packers' => 'Assign a packer first.']);
            }

            $now = now();
            $runId = DB::table('fulfillment_runs')->insertGetId([
                'show_id' => $show->id, 'station_id' => $stationId, 'station_occupied_id' => $stationId,
                'status' => 'packing', 'started_at' => $now, 'created_by' => $actor->id,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            foreach ($packers as $packerId) {
                DB::table('fulfillment_run_packers')->insert(['run_id' => $runId, 'user_id' => $packerId]);
            }

            // Package data is the existing buyer source. A future API adapter can
            // replace this query without changing the station board schema.
            $buyers = FulfillmentPackage::query()->where('show_id', $show->id)
                ->whereNotNull('buyer_username')->where('buyer_username', '!=', '')
                ->select('id', 'buyer_username')->orderBy('id')->get()
                ->groupBy(fn ($package) => mb_strtolower(trim($package->buyer_username)))
                ->sortKeys();

            $index = 0;
            foreach ($buyers as $group) {
                $letter = self::letter($index++);
                DB::table('fulfillment_run_buyers')->insert([
                    'run_id' => $runId, 'buyer_username' => $group->first()->buyer_username,
                    'letter' => $letter, 'order_ids' => json_encode($group->pluck('id')->all()),
                    'item_count' => (int) DB::table('fulfillment_package_items')
                        ->whereIn('fulfillment_package_id', $group->pluck('id'))->sum('quantity'),
                    'status' => 'waiting', 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
            return $runId;
        });
    }

    public static function letter(int $index): string
    {
        if ($index < 0) throw new \InvalidArgumentException('Index must be nonnegative.');
        $result = '';
        do {
            $result = chr(65 + $index % 26) . $result;
            $index = intdiv($index, 26) - 1;
        } while ($index >= 0);
        return $result;
    }

    public function markPacked(int $runId, int $buyerId, User $actor): void
    {
        DB::transaction(function () use ($runId, $buyerId, $actor) {
            $run = DB::table('fulfillment_runs')->where('id', $runId)->lockForUpdate()->first();
            if (! $run || $run->status !== 'packing') abort(409);
            $allowed = $actor->isOwner() || $actor->isAdmin() || $actor->isFulfillmentAdmin()
                || DB::table('fulfillment_run_packers')->where('run_id', $runId)->where('user_id', $actor->id)->exists();
            if (! $allowed) abort(403);
            $buyer = DB::table('fulfillment_run_buyers')->where('run_id', $runId)->where('id', $buyerId)->lockForUpdate()->first();
            if (! $buyer || $buyer->status === 'held') abort(409);
            if ($buyer->status === 'packed') return;
            DB::table('fulfillment_run_buyers')->where('id', $buyerId)->update([
                'status' => 'packed', 'packed_by' => $actor->id,
                'packing_started_at' => $buyer->packing_started_at ?? now(),
                'packed_at' => now(), 'updated_at' => now(),
            ]);
        });
    }

    public function finish(int $runId, User $actor): void
    {
        DB::transaction(function () use ($runId, $actor) {
            $run = DB::table('fulfillment_runs')->where('id', $runId)->lockForUpdate()->first();
            if (! $run || $run->status !== 'packing') abort(409);
            $allowed = $actor->isOwner() || $actor->isAdmin() || $actor->isFulfillmentAdmin()
                || DB::table('fulfillment_run_packers')->where('run_id', $runId)->where('user_id', $actor->id)->exists();
            if (! $allowed) abort(403);
            if (DB::table('fulfillment_run_buyers')->where('run_id', $runId)->where('status', '!=', 'packed')->exists()) {
                throw ValidationException::withMessages(['buyers' => 'Pack or resolve every buyer before finishing.']);
            }
            DB::table('fulfillment_runs')->where('id', $runId)->update([
                'status' => 'done', 'station_occupied_id' => null,
                'finished_at' => now(), 'updated_at' => now(),
            ]);
        });
    }
}
