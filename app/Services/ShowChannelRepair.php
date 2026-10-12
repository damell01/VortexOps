<?php

namespace App\Services;

use App\Models\Shipment;
use App\Models\Show;
use App\Models\ShowIngestionLog;
use App\Models\WhatnotChannel;
use App\Models\WhatnotShowOrder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Puts shows back on the channel they really ran on, and splits shows that
 * swallowed another channel's stream.
 *
 * Until the import was fixed, a Vortex Shop show with the same title and date
 * as a Vortex Breaks show was folded into the Breaks one: its figures, link
 * and orders landed on the Breaks show. Each Whatnot stream has its own live
 * id, so the evidence survives:
 *
 *  - every hourly import logged the row it scraped (with its live id) and the
 *    channel whose account the scraper had switched to and verified;
 *  - the nightly Seller Hub discovery records which channels' listings showed
 *    each live id (_seller_hub_channels).
 *
 * A live id seen under exactly one channel belongs to that channel. One seen
 * under several is left for a person to decide. Nightly analytics logs are not
 * used as evidence: they are fetched under the show's current channel, so they
 * repeat whatever mistake is being fixed.
 *
 * Splitting creates the missing show with the figures Whatnot reported for it,
 * moves its orders, shipments and log entries across, and restores the
 * original show's own figures. A show that has gone through approval or pay
 * keeps its figures and is noted for checking instead.
 */
class ShowChannelRepair
{
    private const UUID = '/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i';

    private const FIGURES = [
        'gross_revenue', 'whatnot_net', 'tips', 'units_sold', 'show_duration', 'completed_earnings',
        'avg_order_value', 'giveaway_spend', 'giveaways_count', 'buyers_count', 'first_time_buyers',
        'returning_buyers', 'shares_count', 'max_concurrent_viewers', 'total_views', 'avg_order_rating',
    ];

    private const LOCKED_STATUSES = ['pending_approval', 'reconciled', 'closed'];

    /**
     * What would change, one entry per show that needs attention.
     *
     * @return list<array{show: Show, actions: list<array<string, mixed>>}>
     */
    public function plan(): array
    {
        $channels = WhatnotChannel::pluck('name', 'id');
        $plans = [];

        foreach ($this->candidates() as $show) {
            $evidence = $this->evidence($show);
            // The stream this record keeps: the one seen only under the channel
            // the show is on (so its streamer report and pay stay with it),
            // else its saved live id, else the first stream imported.
            $own = $evidence->filter(fn ($seen) => $seen['channels'] === [(int) $show->whatnot_channel_id])->keys()->first()
                ?? $this->ownLiveId($show)
                ?? $evidence->sortBy('first_log_id')->keys()->first();
            $actions = [];

            foreach ($evidence as $liveId => $seen) {
                if ($liveId === $own) {
                    continue;
                }
                $existing = Show::where('whatnot_show_id', $liveId)->where('id', '!=', $show->id)->first();
                if (count($seen['channels']) !== 1) {
                    $actions[] = ['type' => 'review', 'live_id' => $liveId, 'reason' => 'Another stream ('.$liveId.') was merged into this show, but it was seen under '.$this->names($seen['channels'], $channels).'.'];
                    continue;
                }
                $actions[] = [
                    'type' => $existing ? 'move_to_existing' : 'split',
                    'live_id' => $liveId,
                    'channel_id' => $seen['channels'][0],
                    'channel' => $channels[$seen['channels'][0]] ?? '#'.$seen['channels'][0],
                    'existing_id' => $existing?->id,
                    'payload' => $seen['payload'],
                    'orders' => WhatnotShowOrder::where('show_id', $show->id)->where('whatnot_show_url', 'like', '%'.$liveId.'%')->count(),
                ];
            }

            $ownChannels = $own ? ($evidence[$own]['channels'] ?? []) : [];
            if (count($ownChannels) === 1 && (int) $ownChannels[0] !== (int) $show->whatnot_channel_id) {
                $actions[] = ['type' => 'move', 'channel_id' => $ownChannels[0], 'channel' => $channels[$ownChannels[0]] ?? '#'.$ownChannels[0]];
            } elseif (count($ownChannels) > 1) {
                $actions[] = ['type' => 'review', 'live_id' => $own, 'reason' => 'This stream was seen under '.$this->names($ownChannels, $channels).'.'];
            }

            $splitting = collect($actions)->whereIn('type', ['split', 'move_to_existing'])->isNotEmpty();
            if ($splitting && $own && isset($evidence[$own])) {
                $actions[] = $this->locked($show)
                    ? ['type' => 'check_figures', 'reason' => 'Show is '.$show->status.' or already paid, so its figures were left as they are; check they only cover this stream.']
                    : ['type' => 'restore_figures', 'live_id' => $own, 'payload' => $evidence[$own]['payload']];
            }

            if ($actions === [] && $show->channel_attribution_suspect && $own && count($ownChannels) === 1) {
                $actions[] = ['type' => 'confirm']; // Evidence agrees with the channel it is on.
            }

            if ($actions !== []) {
                $plans[] = ['show' => $show, 'actions' => $actions];
            }
        }

        return $plans;
    }

    /** Carry out a plan. @return array{split: int, moved: int, confirmed: int, review: int, orders_moved: int} */
    public function apply(array $plans): array
    {
        $totals = ['split' => 0, 'moved' => 0, 'confirmed' => 0, 'review' => 0, 'orders_moved' => 0];

        foreach ($plans as ['show' => $show, 'actions' => $actions]) {
            DB::transaction(function () use ($show, $actions, &$totals) {
                $needsReview = false;
                foreach ($actions as $action) {
                    switch ($action['type']) {
                        case 'split':
                        case 'move_to_existing':
                            // The record may hold the other stream's live id (taken from
                            // its overwritten link); hand it over before reusing it.
                            if (strcasecmp((string) $show->whatnot_show_id, $action['live_id']) === 0) {
                                $show->forceFill(['whatnot_show_id' => null])->save();
                            }
                            $target = $action['type'] === 'split'
                                ? $this->createSplit($show, $action)
                                : Show::findOrFail($action['existing_id']);
                            $totals['orders_moved'] += $this->moveStream($show, $target, $action['live_id']);
                            $totals['split']++;
                            break;
                        case 'move':
                            $show->trackChanges(['whatnot_channel_id' => $action['channel_id']], 'channel_repair');
                            $show->forceFill(['whatnot_channel_id' => $action['channel_id']])->save();
                            $totals['moved']++;
                            break;
                        case 'restore_figures':
                            $this->restoreFigures($show, $action['live_id'], $action['payload']);
                            break;
                        case 'check_figures':
                            $show->forceFill(['revision_notes' => trim(($show->revision_notes ? $show->revision_notes."\n" : '').now()->format('M j, Y g:ia').' — Channel repair: another channel\'s stream was split out of this show. '.$action['reason'])])->save();
                            $needsReview = true;
                            break;
                        case 'review':
                            $needsReview = true;
                            break;
                        case 'confirm':
                            $totals['confirmed']++;
                            break;
                    }
                }
                $show->forceFill(['channel_attribution_suspect' => $needsReview])->save();
                $totals['review'] += (int) $needsReview;
            });
        }

        return $totals;
    }

    /**
     * Copies made by the nightly discovery before it could recognise
     * hourly-imported shows: the hourly show holds the live id only in its
     * link, and discovery added a second, correctly dated show with that id.
     *
     * @return list<array{show: Show, copy: Show, blocked: ?string}>
     */
    public function duplicatePlan(): array
    {
        $plans = [];
        Show::query()->whereNull('whatnot_show_id')->where('detail_url', 'like', '%/live/%')
            ->with('channel:id,name')->orderBy('id')
            ->each(function (Show $original) use (&$plans) {
                if (! preg_match(self::UUID, (string) $original->detail_url, $m)) {
                    return;
                }
                $copy = Show::query()->where('whatnot_show_id', strtolower($m[0]))->where('id', '>', $original->id)->first();
                if (! $copy) {
                    return;
                }
                $blocked = match (true) {
                    $copy->payouts()->exists() => 'the copy has pay attached',
                    $copy->streamerLogEntry()->exists() => 'the copy has a streamer report',
                    $copy->deductionRequests()->exists() => 'the copy has items logged against it',
                    default => null,
                };
                $plans[] = ['show' => $original, 'copy' => $copy, 'blocked' => $blocked];
            });

        return $plans;
    }

    /** Fold each copy into its original: correct date, time and live id; keep the original's report and pay. @return array{merged: int, review: int} */
    public function applyDuplicates(array $plans): array
    {
        $totals = ['merged' => 0, 'review' => 0];
        foreach ($plans as ['show' => $show, 'copy' => $copy, 'blocked' => $blocked]) {
            if ($blocked) {
                $show->forceFill(['channel_attribution_suspect' => true, 'revision_notes' => trim(($show->revision_notes ? $show->revision_notes."\n" : '').now()->format('M j, Y g:ia')." — Show #{$copy->id} is a second copy of this stream, but {$blocked}; merge them by hand.")])->save();
                $totals['review']++;
                continue;
            }
            try {
            DB::transaction(function () use ($show, $copy) {
                $liveId = $copy->whatnot_show_id;
                $raw = is_array($show->raw_import_payload) ? $show->raw_import_payload : [];
                $copyRaw = is_array($copy->raw_import_payload) ? $copy->raw_import_payload : [];
                $channels = array_values(array_unique(array_map('intval', array_merge((array) ($raw['_seller_hub_channels'] ?? []), (array) ($copyRaw['_seller_hub_channels'] ?? [])))));
                $fields = array_filter([
                    'show_date' => $copy->show_date?->toDateString(),
                    'start_time' => $copy->getRawOriginal('start_time'),
                    'end_time' => $copy->getRawOriginal('end_time'),
                ], fn ($v) => $v !== null);

                WhatnotShowOrder::where('show_id', $copy->id)->update(['show_id' => $show->id]);
                Shipment::where('show_id', $copy->id)->update(['show_id' => $show->id]);
                ShowIngestionLog::where('show_id', $copy->id)->update(['show_id' => $show->id]);
                $copy->streamers()->detach();
                $copy->forceFill(['whatnot_show_id' => null])->save();
                $copy->delete();

                $show->trackChanges($fields + ['whatnot_show_id' => $liveId], 'channel_repair');
                $show->forceFill($fields + [
                    'whatnot_show_id' => $liveId,
                    'raw_import_payload' => array_merge($raw, array_intersect_key($copyRaw, array_flip(['_seller_hub_state', '_seller_hub_seen_at'])), $channels ? ['_seller_hub_channels' => $channels] : []),
                ])->save();
            });
            } catch (\Throwable $e) {
                $show->forceFill(['channel_attribution_suspect' => true])->save();
                $totals['review']++;
                report($e);
                continue;
            }
            $totals['merged']++;
        }

        return $totals;
    }

    /** Shows flagged for channel review, or with more than one stream logged against them. */
    private function candidates(): Collection
    {
        $multi = ShowIngestionLog::query()->where('source', 'whatnot')->where('status', 'success')
            ->whereNotNull('show_id')->select('show_id')->groupBy('show_id')->havingRaw('count(*) > 1')->pluck('show_id');

        return Show::query()
            ->where(fn ($q) => $q->where('channel_attribution_suspect', true)->orWhereIn('id', $multi))
            ->with('channel:id,name')
            ->orderBy('show_date')->orderBy('id')->get();
    }

    /**
     * Every stream logged against a show: live id => channels it was seen
     * under, its latest scraped row, and the first log that mentions it.
     *
     * @return Collection<string, array{channels: list<int>, payload: array, first_log_id: int}>
     */
    private function evidence(Show $show): Collection
    {
        $seen = [];
        $logs = ShowIngestionLog::query()->where('show_id', $show->id)->where('source', 'whatnot')
            ->where('status', 'success')->orderBy('id')->get(['id', 'whatnot_channel_id', 'raw_payload']);

        foreach ($logs as $log) {
            $payload = is_array($log->raw_payload) ? $log->raw_payload : [];
            $liveId = $this->liveIdFrom($payload);
            $channel = (int) ($payload['_channel_id'] ?? $log->whatnot_channel_id ?? 0);
            if (! $liveId || ! $channel) {
                continue;
            }
            $seen[$liveId] ??= ['channels' => [], 'payload' => [], 'first_log_id' => $log->id];
            $seen[$liveId]['channels'][] = $channel;
            $seen[$liveId]['payload'] = $payload;
        }

        // The nightly Seller Hub listing, per verified account.
        if ($own = $this->ownLiveId($show)) {
            $raw = is_array($show->raw_import_payload) ? $show->raw_import_payload : [];
            foreach ((array) ($raw['_seller_hub_channels'] ?? []) as $channel) {
                $seen[$own] ??= ['channels' => [], 'payload' => [], 'first_log_id' => PHP_INT_MAX];
                $seen[$own]['channels'][] = (int) $channel;
            }
        }

        return collect($seen)->map(function ($s) {
            $s['channels'] = array_values(array_unique(array_filter($s['channels'])));

            return $s;
        });
    }

    private function ownLiveId(Show $show): ?string
    {
        if ($show->whatnot_show_id) {
            return strtolower($show->whatnot_show_id);
        }
        // The link is overwritten by whichever stream was imported last, so
        // only trust it when nothing else was logged against the show.
        return null;
    }

    private function liveIdFrom(array $payload): ?string
    {
        foreach (['whatnot_live_id', 'live_id', 'whatnot_show_id'] as $key) {
            if (! empty($payload[$key]) && preg_match(self::UUID, (string) $payload[$key], $m)) {
                return strtolower($m[0]);
            }
        }
        foreach (['detail_url', 'url', 'open_url'] as $key) {
            if (! empty($payload[$key]) && preg_match(self::UUID, (string) $payload[$key], $m)) {
                return strtolower($m[0]);
            }
        }

        return null;
    }

    private function createSplit(Show $from, array $action): Show
    {
        $p = $action['payload'];
        $figures = array_filter(array_intersect_key($p, array_flip(self::FIGURES)), fn ($v) => $v !== null && $v !== '');

        $show = Show::create($figures + [
            'whatnot_channel_id' => $action['channel_id'],
            'whatnot_show_id' => $action['live_id'],
            'title' => trim((string) ($p['title'] ?? '')) ?: $from->title,
            'show_date' => $p['show_date'] ?? $from->show_date?->toDateString(),
            'start_time' => $p['start_time'] ?? null,
            'end_time' => $p['end_time'] ?? null,
            'detail_url' => $p['detail_url'] ?? 'https://www.whatnot.com/dashboard/live/'.$action['live_id'],
            'import_source' => 'auto_whatnot',
            'status' => ((int) ($p['units_sold'] ?? 0)) > 0 ? 'mapping' : 'draft',
            'created_by' => auth()->id() ?? 1,
            'raw_import_payload' => $p + ['_split_from_show_id' => $from->id, '_split_at' => now()->toIso8601String()],
        ]);
        $show->detectStreamers();

        return $show;
    }

    /** Move one stream's orders, their shipments and its log entries. @return int orders moved */
    private function moveStream(Show $from, Show $to, string $liveId): int
    {
        $orderIds = WhatnotShowOrder::where('show_id', $from->id)->where('whatnot_show_url', 'like', '%'.$liveId.'%')->pluck('id');
        if ($orderIds->isNotEmpty()) {
            WhatnotShowOrder::whereIn('id', $orderIds)->update(['show_id' => $to->id, 'show_date' => $to->show_date]);
            // shipments.whatnot_order_id is the order row's id.
            Shipment::where('show_id', $from->id)->whereIn('whatnot_order_id', $orderIds)->update(['show_id' => $to->id]);
        }

        ShowIngestionLog::where('show_id', $from->id)->get(['id', 'raw_payload'])
            ->filter(fn ($log) => $this->liveIdFrom(is_array($log->raw_payload) ? $log->raw_payload : []) === $liveId)
            ->each(fn ($log) => $log->forceFill(['show_id' => $to->id])->save());

        return $orderIds->count();
    }

    private function restoreFigures(Show $show, string $liveId, array $payload): void
    {
        $figures = array_filter(array_intersect_key($payload, array_flip(self::FIGURES)), fn ($v) => $v !== null && $v !== '');
        $fields = $figures + array_filter([
            'detail_url' => $payload['detail_url'] ?? null,
            'whatnot_show_id' => Show::where('whatnot_show_id', $liveId)->where('id', '!=', $show->id)->exists() ? null : $liveId,
        ]);
        if ($fields !== []) {
            $show->trackChanges($fields, 'channel_repair');
            $show->forceFill($fields)->save();
        }
    }

    private function locked(Show $show): bool
    {
        return in_array($show->status, self::LOCKED_STATUSES, true)
            || $show->payouts()->whereHas('batch', fn ($q) => $q->where('status', '!=', 'draft'))->exists();
    }

    private function names(array $ids, Collection $channels): string
    {
        return collect($ids)->map(fn ($id) => $channels[$id] ?? '#'.$id)->join(' and ');
    }
}
