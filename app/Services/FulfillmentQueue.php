<?php

namespace App\Services;

use App\Models\FulfillmentRun;
use App\Models\Setting;
use App\Models\Show;
use App\Models\Shipment;
use App\Models\User;
use App\Models\WhatnotShowOrder;
use App\Notifications\VortexAlert;
use App\Support\ChannelContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Which shows are fulfillment work, and the run record that tracks each one.
 *
 * A show is ready to pack once it has ended and Whatnot data (orders or
 * shipments) or an approved streamer report says there is something to ship.
 * Counts come only from that data: a total that is not known stays null and is
 * shown as pending, never guessed.
 */
final class FulfillmentQueue
{
    /** How far back the queue looks for shows that still need packing. */
    public const WINDOW_DAYS = 21;

    /** Shows that are fulfillment work: ended, recent, not cancelled, with something to ship. */
    public function readyShowsQuery(): Builder
    {
        $query = Show::query()
            ->where('is_operational', true)
            ->readyForAnalytics()
            ->whereDate('show_date', '>=', today()->subDays(self::WINDOW_DAYS))
            ->where(function (Builder $work) {
                $work->whereHas('orders', fn (Builder $orders) => $orders->whereNotNull('buyer_username')->where('buyer_username', '!=', ''))
                    ->orWhereHas('shipments')
                    ->orWhereHas('streamerLogEntry', fn (Builder $log) => $log
                        ->where(fn (Builder $approved) => $approved->where('status', 'admin_approved')->orWhere('approval_status', 'approved'))
                        ->whereHas('items'));
            });

        if (ChannelContext::isScoped()) {
            $query->where('whatnot_channel_id', ChannelContext::currentId());
        }

        return $query;
    }

    /** Whether packing must wait for the streamer report to be approved (the long-standing rule). */
    public static function requiresApprovedReport(): bool
    {
        return Setting::get('fulfillment_require_report_approval', '1') !== '0';
    }

    public static function reportApproved(Show $show): bool
    {
        return $show->streamerLogEntry()->where(fn ($q) => $q->where('status', 'admin_approved')->orWhere('approval_status', 'approved'))
            ->whereHas('items')->exists();
    }

    /** Counts straight from the data; null when the data does not say. */
    public function counts(Show $show): array
    {
        $buyers = app(FulfillmentBuyerSource::class)->forShow($show);
        $orders = WhatnotShowOrder::query()->where('show_id', $show->id)->whereNotNull('whatnot_order_id')
            ->distinct()->count('whatnot_order_id');
        $shipments = Shipment::query()->where('show_id', $show->id)->count();

        return [
            'buyer_count' => $buyers->isNotEmpty() ? $buyers->count() : null,
            'order_count' => $orders > 0 ? $orders : null,
            'expected_packages' => $shipments > 0 ? $shipments : null,
        ];
    }

    /** The open run for a show, created (status ready) when there is none. */
    public function ensureRun(Show $show, ?int $actorId = null): FulfillmentRun
    {
        $existing = FulfillmentRun::query()->where('show_id', $show->id)
            ->whereNotIn('status', [FulfillmentRun::CANCELLED])->latest('id')->first();
        if ($existing) return $existing;

        return DB::transaction(function () use ($show, $actorId) {
            // Locks the show row so two syncs cannot create two runs for it.
            Show::query()->whereKey($show->id)->lockForUpdate()->first();
            $existing = FulfillmentRun::query()->where('show_id', $show->id)
                ->whereNotIn('status', [FulfillmentRun::CANCELLED])->latest('id')->first();
            if ($existing) return $existing;

            $run = FulfillmentRun::query()->create([
                'show_id' => $show->id, 'status' => FulfillmentRun::READY, 'created_by' => $actorId,
                'ready_at' => now(),
            ] + $this->counts($show));
            FulfillmentLog::record('ready', $run->id, $actorId, $this->counts($show));

            return $run;
        });
    }

    /** Refresh a not-yet-started run's counts from the latest synced data. */
    public function refreshCounts(FulfillmentRun $run): void
    {
        if ($run->status !== FulfillmentRun::READY || ! $run->show) return;
        $run->forceFill($this->counts($run->show))->save();
    }

    /**
     * Create runs for newly ready shows and tell the right people. Run by the
     * scheduler; safe to run as often as wanted. Only shows that became ready
     * recently are announced, so the first run does not flood anyone.
     *
     * @return int runs created
     */
    public function sync(): int
    {
        $created = 0;
        $this->readyShowsQuery()->whereDoesntHave('fulfillmentRuns')
            ->with(['fulfillmentUsers:id,name', 'channel:id,name'])
            ->orderBy('show_date')->limit(200)->get()
            ->each(function (Show $show) use (&$created) {
                $run = $this->ensureRun($show);
                $created++;
                if ($show->show_date && $show->show_date->greaterThanOrEqualTo(today()->subDays(2))) {
                    $this->announceReady($run, $show);
                }
            });

        return $created;
    }

    public function announceReady(FulfillmentRun $run, Show $show): void
    {
        if ($run->ready_notified_at) return;
        $assigned = $show->fulfillmentUsers()->get();
        $packages = $run->expected_packages !== null ? $run->expected_packages . ' packages' : 'package count pending';
        Notifier::send('fulfillment_show_ready', new VortexAlert(
            event: 'fulfillment_show_ready',
            title: 'Ready to pack: ' . $show->title,
            body: ($show->channel?->name ? $show->channel->name . ' · ' : '') . $packages
                . ($assigned->isEmpty() ? ' · nobody assigned yet' : ''),
            tone: 'info',
            links: ['Open' => \App\Filament\Pages\FulfillmentHome::getUrl()],
            icon: 'heroicon-o-archive-box',
        ), $assigned);
        $run->forceFill(['ready_notified_at' => now()])->save();
    }

    /** The ready-to-pack queue as plain rows for the Home page. */
    public function queueRows(?User $viewer, int $limit = 50): Collection
    {
        $manager = \App\Support\FulfillmentAccess::isManager($viewer);
        $runs = FulfillmentRun::query()
            ->whereIn('status', FulfillmentRun::OPEN)
            ->whereHas('show', fn (Builder $show) => $show->whereIn('shows.id', $this->readyShowsQuery()->select('shows.id')))
            ->with(['show:id,title,show_date,start_time,whatnot_channel_id,status', 'show.channel:id,name', 'show.streamers:id,name',
                'show.fulfillmentUsers:id,name', 'station:id,name', 'packers:id,name'])
            ->when(! $manager && $viewer, fn (Builder $q) => $q->whereHas('show.fulfillmentUsers', fn ($u) => $u->where('users.id', $viewer->id)))
            ->orderByRaw("CASE status WHEN 'packing' THEN 0 WHEN 'paused' THEN 1 ELSE 2 END")
            ->orderBy('ready_at')
            ->limit($limit)->get();

        return $runs;
    }
}
