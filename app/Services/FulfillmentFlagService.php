<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\VortexAlert;
use App\Support\FulfillmentAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Packing issues: flagged by whoever finds them, resolved by a manager, with a
 * trail. A blocking issue on a buyer holds that buyer, and an open blocking
 * issue on a run stops it finishing, so nothing is bypassed quietly.
 */
final class FulfillmentFlagService
{
    public const CATEGORIES = [
        'missing_item' => 'Missing item',
        'wrong_item' => 'Wrong item',
        'damaged' => 'Damaged item',
        'barcode_mismatch' => 'Barcode mismatch',
        'label_issue' => 'Shipping label issue',
        'order_discrepancy' => 'Order discrepancy',
        'other' => 'Other',
    ];

    /** Kept so older flags still read correctly. */
    public const LEGACY = ['wrong_count' => 'Wrong count', 'cancelled' => 'Cancelled'];

    public const TYPES = ['missing_item', 'wrong_item', 'damaged', 'barcode_mismatch', 'label_issue', 'order_discrepancy', 'other', 'wrong_count', 'cancelled'];

    public static function label(string $type): string
    {
        return self::CATEGORIES[$type] ?? self::LEGACY[$type] ?? ucfirst(str_replace('_', ' ', $type));
    }

    public function report(User $actor, int $showId, string $type, string $details, ?int $runId = null, ?int $buyerId = null, ?string $orderNumber = null, ?int $packageId = null, bool $blocking = true): int
    {
        abort_unless(FulfillmentAccess::isMember($actor), 403);
        if (! in_array($type, self::TYPES, true) || trim($details) === '') {
            throw ValidationException::withMessages(['details' => 'Choose a valid issue type and enter details.']);
        }
        if ($runId && ! DB::table('fulfillment_runs')->where('id', $runId)->where('show_id', $showId)->exists()) {
            throw ValidationException::withMessages(['run_id' => 'Run does not belong to this show.']);
        }
        if ($buyerId && (! $runId || ! DB::table('fulfillment_run_buyers')->where('id', $buyerId)->where('run_id', $runId)->exists())) {
            throw ValidationException::withMessages(['buyer_id' => 'Buyer does not belong to this run.']);
        }
        if ($packageId && (! $runId || ! DB::table('fulfillment_run_packages')->where('id', $packageId)->where('run_id', $runId)->exists())) {
            throw ValidationException::withMessages(['package_id' => 'Package does not belong to this run.']);
        }
        if (! FulfillmentAccess::isManager($actor) && (! $runId || ! FulfillmentAccess::canWorkRun($actor, $runId))) {
            abort(403);
        }

        $id = DB::transaction(function () use ($actor, $showId, $type, $details, $runId, $buyerId, $orderNumber, $packageId, $blocking) {
            $stationId = $runId ? DB::table('fulfillment_runs')->where('id', $runId)->value('station_id') : null;
            $id = DB::table('fulfillment_flags')->insertGetId([
                'show_id' => $showId, 'run_id' => $runId, 'buyer_id' => $buyerId, 'package_id' => $packageId,
                'station_id' => $stationId, 'reported_by' => $actor->id, 'type' => $type,
                'details' => trim($details), 'order_number' => $orderNumber ? trim($orderNumber) : null,
                'blocking' => $blocking, 'status' => 'open', 'created_at' => now(), 'updated_at' => now(),
            ]);
            if ($buyerId && $blocking) {
                DB::table('fulfillment_run_buyers')->where('id', $buyerId)->where('status', '!=', 'packed')
                    ->update(['status' => 'held', 'updated_at' => now()]);
            }
            FulfillmentLog::record('issue_flagged', $runId, $actor->id, ['show_id' => $showId, 'flag_id' => $id, 'type' => $type, 'blocking' => $blocking], $buyerId, $packageId);

            return $id;
        });

        $show = \App\Models\Show::query()->find($showId, ['id', 'title']);
        Notifier::send('fulfillment_issue_flagged', new VortexAlert(
            event: 'fulfillment_issue_flagged',
            title: self::label($type) . ($show ? ' · ' . $show->title : ''),
            body: trim($details) . ($orderNumber ? "\nOrder {$orderNumber}" : '') . "\nReported by {$actor->name}",
            tone: 'warning',
            links: ['Review issue' => \App\Filament\Pages\FulfillmentActivity::getUrl(['tab' => 'issues'])],
            icon: 'heroicon-o-flag',
        ));

        return $id;
    }

    public function resolve(User $actor, int $flagId, string $resolution): void
    {
        abort_unless(FulfillmentAccess::isManager($actor), 403);
        if (trim($resolution) === '') throw ValidationException::withMessages(['resolution' => 'Resolution is required.']);

        $flag = DB::transaction(function () use ($actor, $flagId, $resolution) {
            $flag = DB::table('fulfillment_flags')->where('id', $flagId)->lockForUpdate()->first();
            abort_unless($flag, 404);
            if ($flag->status === 'resolved') return null;
            DB::table('fulfillment_flags')->where('id', $flagId)->update([
                'status' => 'resolved', 'resolution' => trim($resolution),
                'resolved_by' => $actor->id, 'resolved_at' => now(), 'updated_at' => now(),
            ]);
            if ($flag->buyer_id && ! DB::table('fulfillment_flags')->where('buyer_id', $flag->buyer_id)->where('status', 'open')->exists()) {
                DB::table('fulfillment_run_buyers')->where('id', $flag->buyer_id)->where('status', 'held')
                    ->update(['status' => 'waiting', 'updated_at' => now()]);
            }
            FulfillmentLog::record('issue_resolved', $flag->run_id, $actor->id, ['show_id' => $flag->show_id, 'flag_id' => $flagId, 'resolution' => trim($resolution)], $flag->buyer_id, $flag->package_id ?? null);

            return $flag;
        });

        if ($flag && ($reporter = User::query()->find($flag->reported_by)) && $reporter->id !== $actor->id) {
            Notifier::send('fulfillment_issue_resolved', new VortexAlert(
                event: 'fulfillment_issue_resolved',
                title: 'Issue resolved: ' . self::label($flag->type),
                body: trim($resolution) . "\nResolved by {$actor->name}",
                tone: 'success',
                links: $flag->run_id ? ['Back to station' => \App\Filament\Pages\FulfillmentStationBoard::getUrl(['run' => $flag->run_id])] : [],
                icon: 'heroicon-o-check-circle',
            ), [$reporter]);
        }
    }
}
