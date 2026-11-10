<?php

namespace App\Services;

use App\Models\FulfillmentRun;
use App\Models\Show;
use App\Models\Shipment;
use App\Models\User;
use App\Notifications\VortexAlert;
use App\Support\FulfillmentAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Everything that happens to a show's fulfillment run, in one place.
 *
 * Each action checks who may do it, changes the run inside a transaction with
 * the rows locked, and writes the activity log, so the tracking record fills
 * itself in as people work: nobody keeps a paper sheet.
 */
class FulfillmentRunService
{
    /** Assign (or reassign) people, and optionally a station, to a show. Managers only. */
    public function assign(Show $show, array $userIds, User $actor, ?int $stationId = null): FulfillmentRun
    {
        abort_unless(FulfillmentAccess::isManager($actor), 403);
        $userIds = array_values(array_unique(array_map('intval', $userIds)));
        $valid = User::query()->whereIn('id', $userIds)->get()->filter(fn (User $u) => FulfillmentAccess::isMember($u))->pluck('id')->all();
        if (count($valid) !== count($userIds)) {
            throw ValidationException::withMessages(['packers' => 'Only fulfillment team members can be assigned.']);
        }
        if ($stationId !== null && ! DB::table('fulfillment_stations')->where('id', $stationId)->where('active', true)->exists()) {
            throw ValidationException::withMessages(['station' => 'Station is unavailable.']);
        }

        $run = app(FulfillmentQueue::class)->ensureRun($show, $actor->id);
        $before = $show->fulfillmentUsers()->pluck('users.id')->map(fn ($id) => (int) $id)->all();

        DB::transaction(function () use ($show, $run, $userIds, $stationId, $actor) {
            $show->fulfillmentUsers()->sync($userIds);
            if ($stationId !== null && $run->status === FulfillmentRun::READY) {
                $run->forceFill(['station_id' => $stationId])->save();
            }
            // Someone added while the show is being packed works on it straight away.
            if ($run->isOnStation()) {
                foreach ($userIds as $id) {
                    DB::table('fulfillment_run_packers')->insertOrIgnore(['run_id' => $run->id, 'user_id' => $id]);
                }
            }
            FulfillmentLog::record('assigned', $run->id, $actor->id, ['user_ids' => $userIds, 'station_id' => $stationId ?? $run->station_id]);
        });

        $added = User::query()->whereIn('id', array_diff($userIds, $before))->get();
        if ($added->isNotEmpty()) {
            Notifier::send('fulfillment_assigned', new VortexAlert(
                event: 'fulfillment_assigned',
                title: 'You are assigned: ' . $show->title,
                body: 'Open the Station Board when you are ready to start packing.',
                links: ['Open Station Board' => \App\Filament\Pages\FulfillmentStationBoard::getUrl(['run' => $run->id])],
                icon: 'heroicon-o-archive-box',
            ), $added);
        }

        return $run->fresh();
    }

    public function start(Show $show, int $stationId, User $actor, array $packerIds = []): int
    {
        if (! FulfillmentAccess::isMember($actor)) abort(403);
        $manager = FulfillmentAccess::isManager($actor);

        if ($show->status === 'cancelled') {
            throw ValidationException::withMessages(['show' => 'This show was cancelled.']);
        }
        if (FulfillmentQueue::requiresApprovedReport() && ! FulfillmentQueue::reportApproved($show)) {
            throw ValidationException::withMessages(['show' => 'The streamer report must be approved before packing.']);
        }

        $readyRun = app(FulfillmentQueue::class)->ensureRun($show, $actor->id);

        return DB::transaction(function () use ($show, $stationId, $actor, $packerIds, $manager, $readyRun) {
            $station = DB::table('fulfillment_stations')->where('id', $stationId)->lockForUpdate()->first();
            if (! $station || ! $station->active) {
                throw ValidationException::withMessages(['station' => 'Station is unavailable.']);
            }
            $run = DB::table('fulfillment_runs')->where('id', $readyRun->id)->lockForUpdate()->first();
            if (in_array($run->status, FulfillmentRun::ON_STATION, true)) {
                throw ValidationException::withMessages(['show' => 'Show is already being packed.']);
            }
            if (in_array($run->status, [FulfillmentRun::COMPLETED, FulfillmentRun::APPROVED], true)) {
                throw ValidationException::withMessages(['show' => 'This show has already been packed.']);
            }
            if (DB::table('fulfillment_runs')->where('station_occupied_id', $stationId)->exists()) {
                throw ValidationException::withMessages(['station' => 'Station is already packing another show.']);
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

            $buyers = app(FulfillmentBuyerSource::class)->forShow($show);
            if ($buyers->isEmpty()) {
                throw ValidationException::withMessages(['show' => 'No buyer data is available. Sync orders or shipments before starting.']);
            }

            $now = now();
            DB::table('fulfillment_runs')->where('id', $run->id)->update([
                'station_id' => $stationId, 'station_occupied_id' => $stationId,
                'status' => FulfillmentRun::PACKING, 'started_at' => $now, 'paused_at' => null,
                'created_by' => $run->created_by ?? $actor->id, 'updated_at' => $now,
            ]);
            foreach ($packers as $packerId) {
                DB::table('fulfillment_run_packers')->insertOrIgnore(['run_id' => $run->id, 'user_id' => $packerId]);
            }

            // Freeze the alphabetical buyer letters for the camera trail. A
            // later sync may add orders, but never relabels a started run.
            DB::table('fulfillment_run_buyers')->where('run_id', $run->id)->delete();
            $shipments = Shipment::query()->where('shipments.show_id', $show->id)->whereNotNull('shipments.buyer_username')
                ->leftJoin('whatnot_show_orders as wso', 'wso.id', '=', 'shipments.whatnot_order_id')
                ->orderBy('shipments.id')->get(['shipments.id', 'shipments.buyer_username', 'wso.whatnot_order_id as order_number'])
                ->groupBy(fn (Shipment $s) => mb_strtolower(trim((string) $s->buyer_username)));
            $packageTotal = 0;
            foreach ($buyers->values() as $index => $buyer) {
                $key = mb_strtolower(trim($buyer['username']));
                $buyerShipments = $shipments->get($key, collect());
                $buyerId = DB::table('fulfillment_run_buyers')->insertGetId([
                    'run_id' => $run->id, 'buyer_username' => $buyer['username'],
                    'letter' => self::letter($index), 'order_ids' => json_encode($buyer['order_ids']),
                    'order_count' => count($buyer['order_ids']), 'item_count' => $buyer['item_count'],
                    'package_count' => $buyerShipments->isNotEmpty() ? $buyerShipments->count() : null,
                    'status' => 'waiting', 'created_at' => $now, 'updated_at' => $now,
                ]);
                foreach ($buyerShipments as $shipment) {
                    DB::table('fulfillment_run_packages')->insert([
                        'run_id' => $run->id, 'buyer_id' => $buyerId, 'package_key' => 'shipment:' . $shipment->id,
                        'shipment_id' => $shipment->id, 'order_ids' => json_encode(array_values(array_filter([$shipment->order_number]))),
                        'status' => 'waiting', 'created_at' => $now, 'updated_at' => $now,
                    ]);
                    $packageTotal++;
                }
            }

            $orderCount = (int) $buyers->sum(fn ($b) => count($b['order_ids']));
            DB::table('fulfillment_runs')->where('id', $run->id)->update([
                'buyer_count' => $buyers->count(),
                'order_count' => $orderCount > 0 ? $orderCount : null,
                'expected_packages' => $packageTotal > 0 ? $packageTotal : null,
                'completed_packages' => 0,
            ]);

            FulfillmentLog::record('started', $run->id, $actor->id, ['station_id' => $stationId, 'packers' => $packers]);
            FulfillmentLog::record('buyers_loaded', $run->id, $actor->id, [
                'buyers' => $buyers->count(), 'packages' => $packageTotal ?: null,
                'range' => 'A–' . self::letter(max(0, $buyers->count() - 1)),
            ]);

            return (int) $run->id;
        });
    }

    /** A, B, … Z, AA, AB, … for buyer positions 0, 1, … */
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

    /** Make a buyer the one being packed (what the camera letter shows). */
    public function selectBuyer(int $runId, int $buyerId, User $actor): void
    {
        DB::transaction(function () use ($runId, $buyerId, $actor) {
            $run = $this->lockActiveRun($runId, $actor);
            $buyer = DB::table('fulfillment_run_buyers')->where('run_id', $runId)->where('id', $buyerId)->first();
            abort_unless($buyer, 404);
            if ((int) $run->current_buyer_id === $buyerId) return;

            DB::table('fulfillment_run_buyers')->where('run_id', $runId)->where('status', 'packing')
                ->where('id', '!=', $buyerId)->update(['status' => 'waiting', 'updated_at' => now()]);
            if ($buyer->status === 'waiting') {
                DB::table('fulfillment_run_buyers')->where('id', $buyerId)->update([
                    'status' => 'packing', 'packing_started_at' => $buyer->packing_started_at ?? now(), 'updated_at' => now(),
                ]);
            }
            DB::table('fulfillment_runs')->where('id', $runId)->update(['current_buyer_id' => $buyerId, 'updated_at' => now()]);
            FulfillmentLog::record('buyer_selected', $runId, $actor->id, [], $buyerId);
        });
    }

    /** Mark one package packed. Pressing it again does nothing: a package counts once. */
    public function packPackage(int $runId, int $packageId, User $actor): void
    {
        DB::transaction(function () use ($runId, $packageId, $actor) {
            $this->lockActiveRun($runId, $actor);
            $package = DB::table('fulfillment_run_packages')->where('run_id', $runId)->where('id', $packageId)->lockForUpdate()->first();
            abort_unless($package, 404);
            if ($package->status === 'packed') return;
            $buyer = DB::table('fulfillment_run_buyers')->where('id', $package->buyer_id)->lockForUpdate()->first();
            $this->assertBuyerPackable($runId, $buyer);

            DB::table('fulfillment_run_packages')->where('id', $packageId)->update([
                'status' => 'packed', 'packed_by' => $actor->id, 'packed_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('fulfillment_runs')->where('id', $runId)->increment('completed_packages', 1, ['updated_at' => now()]);
            FulfillmentLog::record('package_packed', $runId, $actor->id, ['package_key' => $package->package_key], $buyer->id, $packageId);

            if (! DB::table('fulfillment_run_packages')->where('buyer_id', $buyer->id)->where('status', '!=', 'packed')->exists()) {
                $this->completeBuyer($runId, $buyer, $actor);
            }
        });
    }

    /** Mark a buyer packed: every package still open for them is packed by this person. */
    public function markPacked(int $runId, int $buyerId, User $actor): void
    {
        DB::transaction(function () use ($runId, $buyerId, $actor) {
            $this->lockActiveRun($runId, $actor);
            $buyer = DB::table('fulfillment_run_buyers')->where('run_id', $runId)->where('id', $buyerId)->lockForUpdate()->first();
            abort_unless($buyer, 404);
            if ($buyer->status === 'packed') return;
            $this->assertBuyerPackable($runId, $buyer);

            $open = DB::table('fulfillment_run_packages')->where('buyer_id', $buyerId)->where('status', '!=', 'packed')->lockForUpdate()->get();
            foreach ($open as $package) {
                DB::table('fulfillment_run_packages')->where('id', $package->id)->update([
                    'status' => 'packed', 'packed_by' => $actor->id, 'packed_at' => now(), 'updated_at' => now(),
                ]);
                FulfillmentLog::record('package_packed', $runId, $actor->id, ['package_key' => $package->package_key], $buyerId, $package->id);
            }
            if ($open->isNotEmpty()) {
                DB::table('fulfillment_runs')->where('id', $runId)->increment('completed_packages', $open->count(), ['updated_at' => now()]);
            }
            $this->completeBuyer($runId, $buyer, $actor);
        });
    }

    /** Managers can put a packed buyer back to waiting (packages too) to fix a mistake. */
    public function reopenBuyer(int $runId, int $buyerId, User $actor, string $reason): void
    {
        abort_unless(FulfillmentAccess::isManager($actor), 403);
        if (trim($reason) === '') throw ValidationException::withMessages(['reason' => 'Say why the buyer is being reopened.']);
        DB::transaction(function () use ($runId, $buyerId, $actor, $reason) {
            $this->lockActiveRun($runId, $actor);
            $buyer = DB::table('fulfillment_run_buyers')->where('run_id', $runId)->where('id', $buyerId)->lockForUpdate()->first();
            abort_unless($buyer, 404);
            $packed = DB::table('fulfillment_run_packages')->where('buyer_id', $buyerId)->where('status', 'packed')->count();
            DB::table('fulfillment_run_packages')->where('buyer_id', $buyerId)->update(['status' => 'waiting', 'packed_by' => null, 'packed_at' => null, 'updated_at' => now()]);
            if ($packed) DB::table('fulfillment_runs')->where('id', $runId)->decrement('completed_packages', $packed);
            DB::table('fulfillment_run_buyers')->where('id', $buyerId)->update(['status' => 'waiting', 'packed_by' => null, 'packed_at' => null, 'updated_at' => now()]);
            FulfillmentLog::record('buyer_reopened', $runId, $actor->id, ['reason' => trim($reason)], $buyerId);
        });
    }

    public function pause(int $runId, User $actor, ?string $reason = null): void
    {
        DB::transaction(function () use ($runId, $actor, $reason) {
            $run = $this->lockActiveRun($runId, $actor);
            if ($run->status === FulfillmentRun::PAUSED) return;
            DB::table('fulfillment_runs')->where('id', $runId)->update(['status' => FulfillmentRun::PAUSED, 'paused_at' => now(), 'updated_at' => now()]);
            FulfillmentLog::record('paused', $runId, $actor->id, array_filter(['reason' => $reason ? trim($reason) : null]));
        });
    }

    public function resume(int $runId, User $actor): void
    {
        DB::transaction(function () use ($runId, $actor) {
            $run = $this->lockActiveRun($runId, $actor, allowPaused: true);
            if ($run->status !== FulfillmentRun::PAUSED) return;
            $paused = $run->paused_at ? (int) \Illuminate\Support\Carbon::parse($run->paused_at)->diffInSeconds(now()) : 0;
            DB::table('fulfillment_runs')->where('id', $runId)->update([
                'status' => FulfillmentRun::PACKING, 'paused_at' => null,
                'paused_seconds' => (int) $run->paused_seconds + max(0, $paused), 'updated_at' => now(),
            ]);
            FulfillmentLog::record('resumed', $runId, $actor->id, ['paused_seconds' => max(0, $paused)]);
        });
    }

    /** Finish packing: final totals, discrepancies recorded, station freed, managers asked to approve. */
    public function finish(int $runId, User $actor): void
    {
        $run = DB::transaction(function () use ($runId, $actor) {
            $run = $this->lockActiveRun($runId, $actor, allowPaused: true);
            if (DB::table('fulfillment_flags')->where('run_id', $runId)->where('status', 'open')->where('blocking', true)->exists()) {
                throw ValidationException::withMessages(['issues' => 'Resolve open issues before finishing the show.']);
            }
            if (! DB::table('fulfillment_run_buyers')->where('run_id', $runId)->exists()) {
                throw ValidationException::withMessages(['buyers' => 'A run cannot finish without any buyers.']);
            }
            if (DB::table('fulfillment_run_buyers')->where('run_id', $runId)->where('status', '!=', 'packed')->exists()) {
                throw ValidationException::withMessages(['buyers' => 'Pack or flag every buyer before finishing.']);
            }

            $pausedExtra = $run->status === FulfillmentRun::PAUSED && $run->paused_at
                ? (int) \Illuminate\Support\Carbon::parse($run->paused_at)->diffInSeconds(now()) : 0;
            $packed = DB::table('fulfillment_run_packages')->where('run_id', $runId)->where('status', 'packed')->count();
            $discrepancies = $this->discrepancies($run, $packed);

            DB::table('fulfillment_runs')->where('id', $runId)->update([
                'status' => FulfillmentRun::COMPLETED, 'station_occupied_id' => null, 'current_buyer_id' => null,
                'paused_at' => null, 'paused_seconds' => (int) $run->paused_seconds + $pausedExtra,
                'finished_at' => now(), 'completed_packages' => $packed,
                'discrepancies' => $discrepancies ? json_encode($discrepancies) : null, 'updated_at' => now(),
            ]);
            FulfillmentLog::record('finished', $runId, $actor->id, ['packages' => $packed, 'discrepancies' => $discrepancies ?: null]);

            return FulfillmentRun::query()->with('show:id,title')->find($runId);
        });

        Notifier::send('fulfillment_show_finished', new VortexAlert(
            event: 'fulfillment_show_finished',
            title: 'Packed: ' . ($run->show?->title ?? 'a show'),
            body: ($run->completed_packages ?: $run->buyer_count) . ' ' . ($run->completed_packages ? 'packages' : 'buyers')
                . ' · ready for final sign-off' . ($run->discrepancies ? ' · ' . count($run->discrepancies) . ' discrepancy to review' : ''),
            tone: $run->discrepancies ? 'warning' : 'success',
            links: ['Sign off' => \App\Filament\Pages\FulfillmentActivity::getUrl(['run' => $run->id])],
            icon: 'heroicon-o-check-badge',
        ));
    }

    /** The packer confirms the completion record is right. */
    public function acknowledge(int $runId, User $actor): void
    {
        DB::transaction(function () use ($runId, $actor) {
            $run = DB::table('fulfillment_runs')->where('id', $runId)->lockForUpdate()->first();
            abort_unless($run, 404);
            $onRun = DB::table('fulfillment_run_packers')->where('run_id', $runId)->where('user_id', $actor->id)->exists();
            abort_unless($onRun || FulfillmentAccess::isManager($actor), 403);
            if (! in_array($run->status, [FulfillmentRun::COMPLETED, FulfillmentRun::APPROVED], true)) {
                throw ValidationException::withMessages(['run' => 'Finish packing before acknowledging.']);
            }
            if ($run->acknowledged_at) return;
            DB::table('fulfillment_runs')->where('id', $runId)->update(['acknowledged_at' => now(), 'acknowledged_by' => $actor->id, 'updated_at' => now()]);
            FulfillmentLog::record('acknowledged', $runId, $actor->id);
        });
    }

    /**
     * The packer's review screen: finish packing and confirm the show is done
     * in one step, so it goes straight to the fulfillment admin for sign-off.
     */
    public function confirmDone(int $runId, User $actor): void
    {
        $this->finish($runId, $actor);
        $this->acknowledge($runId, $actor);
    }

    /**
     * The fulfillment admin's final sign-off: after reviewing the show, one
     * confirm. Same rules as approval (finished, no open issues); the record
     * keeps who signed off and when.
     */
    public function signOff(int $runId, User $actor, ?string $notes = null): void
    {
        $this->approve($runId, $actor, $notes);
    }

    /** A manager approves the completion record (the final sign-off). */
    public function approve(int $runId, User $actor, ?string $notes = null): void
    {
        abort_unless(FulfillmentAccess::isManager($actor), 403);
        DB::transaction(function () use ($runId, $actor, $notes) {
            $run = DB::table('fulfillment_runs')->where('id', $runId)->lockForUpdate()->first();
            abort_unless($run, 404);
            if ($run->status === FulfillmentRun::APPROVED) return;
            if ($run->status !== FulfillmentRun::COMPLETED) {
                throw ValidationException::withMessages(['run' => 'Only a completed show can be approved.']);
            }
            if (DB::table('fulfillment_flags')->where('run_id', $runId)->where('status', 'open')->exists()) {
                throw ValidationException::withMessages(['run' => 'Resolve open issues before approving.']);
            }
            DB::table('fulfillment_runs')->where('id', $runId)->update([
                'status' => FulfillmentRun::APPROVED, 'approved_at' => now(), 'approved_by' => $actor->id,
                'approval_notes' => $notes ? trim($notes) : null, 'updated_at' => now(),
            ]);
            FulfillmentLog::record('approved', $runId, $actor->id, array_filter(['notes' => $notes ? trim($notes) : null]));
        });
    }

    private function discrepancies(object $run, int $packedPackages): array
    {
        $issues = [];
        if ($run->expected_packages !== null && (int) $run->expected_packages !== $packedPackages) {
            $issues[] = "Expected {$run->expected_packages} packages, {$packedPackages} marked packed.";
        }
        $show = Show::query()->find($run->show_id);
        if ($show) {
            $now = app(FulfillmentBuyerSource::class)->forShow($show)->count();
            if ($run->buyer_count !== null && $now !== (int) $run->buyer_count) {
                $issues[] = "Whatnot now lists {$now} buyers; {$run->buyer_count} were loaded when packing started.";
            }
            $unmatched = Shipment::query()->where('show_id', $show->id)->where(fn ($q) => $q->whereNull('buyer_username')->orWhere('buyer_username', ''))->count();
            if ($unmatched > 0) $issues[] = "{$unmatched} shipment(s) have no buyer username and were not on the board.";
        }
        $resolved = DB::table('fulfillment_flags')->where('run_id', $run->id)->count();
        if ($resolved > 0) $issues[] = "{$resolved} issue(s) were flagged during packing.";

        return $issues;
    }

    private function completeBuyer(int $runId, object $buyer, User $actor): void
    {
        DB::table('fulfillment_run_buyers')->where('id', $buyer->id)->update([
            'status' => 'packed', 'packed_by' => $actor->id,
            'packing_started_at' => $buyer->packing_started_at ?? now(), 'packed_at' => now(), 'updated_at' => now(),
        ]);
        FulfillmentLog::record('buyer_packed', $runId, $actor->id, [], $buyer->id);
    }

    private function assertBuyerPackable(int $runId, ?object $buyer): void
    {
        abort_unless($buyer && (int) $buyer->run_id === $runId, 404);
        if ($buyer->status === 'held' || DB::table('fulfillment_flags')->where('buyer_id', $buyer->id)->where('status', 'open')->where('blocking', true)->exists()) {
            throw ValidationException::withMessages(['buyer' => 'Resolve the open buyer issue before confirming packing.']);
        }
        // Once item scanning starts for a buyer, packing waits for every expected item.
        if (DB::table('fulfillment_verifications')->where('run_id', $runId)->where('buyer_id', $buyer->id)->exists()) {
            $coverage = app(FulfillmentVerificationService::class)->coverage($runId, $buyer->id);
            if (! $coverage['complete']) {
                throw ValidationException::withMessages([
                    'buyer' => $coverage['supported']
                        ? 'Scan all expected items before confirming this buyer packed.'
                        : 'This buyer has unmapped order items. A manager must review the order mapping before completing scan verification.',
                ]);
            }
        }
    }

    /** The run, locked, after checking it is being worked and this person may work it. */
    private function lockActiveRun(int $runId, User $actor, bool $allowPaused = false): object
    {
        $run = DB::table('fulfillment_runs')->where('id', $runId)->lockForUpdate()->first();
        if (! $run) abort(404);
        $states = $allowPaused ? FulfillmentRun::ON_STATION : [FulfillmentRun::PACKING];
        if (! in_array($run->status, $states, true)) {
            throw ValidationException::withMessages(['run' => $run->status === FulfillmentRun::PAUSED ? 'Packing is paused. Resume to continue.' : 'This show is not being packed.']);
        }
        abort_unless(FulfillmentAccess::canWorkRun($actor, $runId), 403);

        return $run;
    }
}
