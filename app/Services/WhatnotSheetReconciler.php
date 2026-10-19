<?php

namespace App\Services;

use App\Models\Payout;
use App\Models\Show;
use App\Models\ShowIngestionLog;
use App\Models\WhatnotChannel;
use App\Models\WhatnotShowOrder;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Lines the app's shows up with Whatnot's own Shows exports, one export (or
 * several pages of one) per channel.
 *
 * Whatnot's list for an account is the authority on which shows that account
 * ran. For every row this finds the show it describes:
 *
 *  1. the same title on the same day on that channel;
 *  2. otherwise the same title on the same day sitting on another channel,
 *     as long as that channel's own export does not claim it — the show was
 *     imported into the wrong channel and is moved;
 *  3. either of the above a day either side (a show that ran past midnight);
 *  4. otherwise it is missing from the app and is created.
 *
 * Matched shows take the export's figures (keeping existing cents where the
 * export only rounds to whole dollars). Shows the app holds for a channel on
 * days its export covers, but that the export does not list, are reported
 * rather than deleted.
 *
 * The export has no show id, so two shows with the same title on the same
 * day are told apart by how close their sales and length are.
 */
class WhatnotSheetReconciler
{
    public function __construct(private readonly WhatnotAnalyticsCsvImporter $csv) {}

    /**
     * Rows from one or more exports for one channel, de-duplicated where pages
     * overlap.
     *
     * @param  list<string>  $paths
     * @return list<array{title:string,date:string,key:string,metrics:array<string,float|int>,orders:?int,raw:array<string,string>}>
     */
    /** Per file of the last rows() call: path => rows read, rows new to the set, first and last date. */
    public array $fileStats = [];

    public function rows(array $paths, string $since, ?string $until = null): array
    {
        $rows = [];
        $seen = [];
        $this->fileStats = [];

        foreach ($paths as $path) {
            $this->fileStats[$path] = ['rows' => 0, 'new' => 0, 'first' => null, 'last' => null];
            $handle = fopen($path, 'rb');
            if (! $handle) {
                throw new RuntimeException('Could not open '.basename($path).'.');
            }

            try {
                if (fread($handle, 3) !== "\xEF\xBB\xBF") {
                    rewind($handle);
                }
                $headers = array_map(fn ($h) => trim((string) $h), fgetcsv($handle, 0, ',', '"', '') ?: []);
                if (array_diff(WhatnotAnalyticsCsvImporter::REQUIRED_HEADERS, $headers)) {
                    throw new RuntimeException(basename($path).' is not a Whatnot Shows export (it needs Show and Date columns).');
                }

                while (($values = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                    if ($values === [null] || count($values) !== count($headers)) {
                        continue;
                    }
                    $raw = array_combine($headers, $values);
                    $title = trim((string) $raw['Show']);
                    try {
                        $date = Carbon::createFromFormat('!m/d/Y', trim((string) $raw['Date']));
                    } catch (\Throwable) {
                        continue;
                    }
                    if ($title === '' || ! $date || $date->toDateString() < $since || ($until !== null && $date->toDateString() > $until)) {
                        continue;
                    }

                    $metrics = $this->csv->metrics($raw);
                    // A dash under Est. Sales / Est. Earnings is Whatnot saying
                    // the show it lists sold nothing: that is $0, not unknown.
                    foreach (['gross_revenue' => ['Est. Sales', 'Estimated Sales'], 'whatnot_net' => ['Est. Earnings', 'Estimated Earnings']] as $field => $columns) {
                        foreach ($columns as $column) {
                            if (array_key_exists($column, $raw) && ! isset($metrics[$field]) && in_array(trim((string) $raw[$column]), ['—', '-', '–', ''], true)) {
                                $metrics[$field] = 0.0;
                                break;
                            }
                        }
                    }
                    $orders = $this->csv->number($raw['Orders'] ?? null);
                    // Overlapping pages repeat rows exactly; two different shows
                    // with the same title on one day differ somewhere.
                    $fingerprint = $date->toDateString().'|'.$this->csv->normalize($title).'|'.md5(json_encode([$metrics, $orders]));
                    $stat = &$this->fileStats[$path];
                    $stat['rows']++;
                    $stat['first'] = min($stat['first'] ?? $date->toDateString(), $date->toDateString());
                    $stat['last'] = max($stat['last'] ?? $date->toDateString(), $date->toDateString());
                    if (isset($seen[$fingerprint])) {
                        unset($stat);
                        continue;
                    }
                    $stat['new']++;
                    unset($stat);
                    $seen[$fingerprint] = true;

                    $rows[] = [
                        'title' => $title,
                        'date' => $date->toDateString(),
                        'key' => $this->csv->normalize($title),
                        'metrics' => $metrics,
                        'orders' => $orders === null ? null : (int) $orders,
                        'raw' => $raw,
                    ];
                }
            } finally {
                fclose($handle);
            }
        }

        return $rows;
    }

    /**
     * Work out what lines up and what has to change.
     *
     * @param  array<int, list<string>>  $sheets  channel id => export file paths
     * @return array{channels: array<int, array<string, mixed>>, items: list<array<string, mixed>>}
     */
    public function plan(array $sheets, string $since = '2026-06-01', ?string $until = null): array
    {
        $channels = WhatnotChannel::whereIn('id', array_keys($sheets))->pluck('name', 'id');
        $allNames = WhatnotChannel::pluck('name', 'id');

        $rowsByChannel = [];
        $statsByChannel = [];
        $from = null;
        $to = null;
        foreach ($sheets as $channelId => $paths) {
            $rows = $this->rows($paths, $since, $until);
            $rowsByChannel[$channelId] = $rows;
            $statsByChannel[$channelId] = collect($this->fileStats)->mapWithKeys(fn ($s, $path) => [basename($path) => $s])->all();
            foreach ($rows as $row) {
                $from = min($from ?? $row['date'], $row['date']);
                $to = max($to ?? $row['date'], $row['date']);
            }
        }

        if ($from === null && $until === null) {
            return ['channels' => [], 'items' => []];
        }
        // With the export's own date range given, every channel is checked
        // across all of it, including days after its last show.
        if ($until !== null) {
            $from = $since;
            $to = $until;
        }

        $shows = Show::query()
            ->whereBetween('show_date', [Carbon::parse($from)->subDay()->toDateString(), Carbon::parse($to)->addDay()->toDateString()])
            ->get(['id', 'title', 'show_date', 'whatnot_channel_id', 'status', 'gross_revenue', 'whatnot_net', 'show_duration', 'units_sold',
                'avg_order_value', 'giveaway_spend', 'giveaways_count', 'buyers_count', 'first_time_buyers', 'returning_buyers',
                'shares_count', 'max_concurrent_viewers', 'total_views', 'avg_order_rating', 'completed_earnings', 'analytics_sync_status'])
            ->each(fn (Show $s) => $s->setAttribute('_key', $this->csv->normalize($s->title)));

        $claimed = [];     // show id => true once a row has it
        $pairs = [];       // [channelId, rowIndex, Show, how]
        $unmatched = [];   // channelId => [rowIndex => row]

        // 1. Same channel, same day.
        foreach ($rowsByChannel as $channelId => $rows) {
            $groups = collect($rows)->map(fn ($r, $i) => $r + ['i' => $i])->groupBy(fn ($r) => $r['date'].'|'.$r['key']);
            foreach ($groups as $group) {
                $first = $group->first();
                $candidates = $shows->filter(fn (Show $s) => (int) $s->whatnot_channel_id === (int) $channelId
                    && $s->show_date?->toDateString() === $first['date'] && $s->getAttribute('_key') === $first['key'] && ! isset($claimed[$s->id]));
                foreach ($this->pair($group, $candidates) as [$row, $show]) {
                    if ($show) {
                        $claimed[$show->id] = true;
                        $pairs[] = [$channelId, $row['i'], $show, 'matched'];
                    } else {
                        $unmatched[$channelId][$row['i']] = $row;
                    }
                }
            }
        }

        // 2-3. Another channel (not claimed by its own export), then a day either side.
        foreach ([0, 1] as $tolerance) {
            foreach ($unmatched as $channelId => $rows) {
                foreach ($rows as $i => $row) {
                    $day = Carbon::parse($row['date']);
                    $days = $tolerance ? [$day->copy()->subDay()->toDateString(), $day->copy()->addDay()->toDateString()] : [$row['date']];
                    $candidates = $shows->filter(fn (Show $s) => in_array($s->show_date?->toDateString(), $days, true)
                        && $s->getAttribute('_key') === $row['key'] && ! isset($claimed[$s->id])
                        && ($tolerance || (int) $s->whatnot_channel_id !== (int) $channelId));
                    // Prefer the row's own channel, then the closest figures.
                    $show = $candidates->sortBy(fn (Show $s) => [(int) $s->whatnot_channel_id !== (int) $channelId ? 1 : 0, $this->distance($row, $s)])->first();
                    if ($show) {
                        $claimed[$show->id] = true;
                        $pairs[] = [$channelId, $i, $show, (int) $show->whatnot_channel_id === (int) $channelId ? 'matched_nearby_day' : 'wrong_channel'];
                        unset($unmatched[$channelId][$i]);
                    }
                }
            }
        }

        $orderRows = WhatnotShowOrder::query()
            ->whereIn('show_id', collect($pairs)->map(fn ($p) => $p[2]->id)->merge($shows->pluck('id'))->unique()->values())
            ->selectRaw('show_id, COUNT(*) as n')->groupBy('show_id')->pluck('n', 'show_id');

        $items = [];
        foreach ($pairs as [$channelId, $i, $show, $how]) {
            $row = $rowsByChannel[$channelId][$i];
            $changes = $this->changes($show, $row['metrics']);
            $moving = (int) $show->whatnot_channel_id !== (int) $channelId;
            $items[] = [
                'action' => $moving ? 'move' : ($changes ? 'update' : 'ok'),
                'how' => $how,
                'channel_id' => (int) $channelId,
                'channel' => $channels[$channelId] ?? '#'.$channelId,
                'from_channel' => $moving ? ($allNames[$show->whatnot_channel_id] ?? 'No channel') : null,
                'show_id' => $show->id,
                'title' => $row['title'],
                'date' => $row['date'],
                'app_date' => $show->show_date?->toDateString(),
                'changes' => $changes,
                'metrics' => $row['metrics'],
                'sheet_orders' => $row['orders'],
                'app_orders' => (int) ($orderRows[$show->id] ?? 0),
                'raw' => $row['raw'],
            ];
        }

        foreach ($unmatched as $channelId => $rows) {
            foreach ($rows as $row) {
                $empty = $row['metrics'] === [] || ((float) ($row['metrics']['gross_revenue'] ?? 0) <= 0 && (int) ($row['metrics']['show_duration'] ?? 0) === 0);
                $items[] = [
                    'action' => $empty ? 'skip_empty' : 'create',
                    'how' => 'missing',
                    'channel_id' => (int) $channelId,
                    'channel' => $channels[$channelId] ?? '#'.$channelId,
                    'from_channel' => null,
                    'show_id' => null,
                    'title' => $row['title'],
                    'date' => $row['date'],
                    'app_date' => null,
                    'changes' => [],
                    'metrics' => $row['metrics'],
                    'sheet_orders' => $row['orders'],
                    'app_orders' => 0,
                    'raw' => $row['raw'],
                ];
            }
        }

        // Shows the app has on a covered day that the channel's export does
        // not list. The first day of an export may be cut off by paging, so
        // it is left out of this check.
        $summary = [];
        foreach ($rowsByChannel as $channelId => $rows) {
            $dates = collect($rows)->pluck('date');
            $cFrom = $until !== null ? $since : $dates->min();
            $cTo = $until !== null ? $until : $dates->max();
            $mine = collect($items)->where('channel_id', (int) $channelId);

            $extras = $shows->filter(fn (Show $s) => (int) $s->whatnot_channel_id === (int) $channelId && ! isset($claimed[$s->id])
                && $s->status !== 'cancelled' && $s->show_date
                && ($until !== null ? $s->show_date->toDateString() >= $cFrom : $s->show_date->toDateString() > $cFrom)
                && $s->show_date->toDateString() <= $cTo);
            // Shows the owner checked and confirmed real (Whatnot's analytics
            // leaves some streams out) are listed apart and not flagged again.
            $confirmedIds = $extras->isEmpty() ? collect() : Show::whereKey($extras->modelKeys())
                ->where('raw_import_payload->_sheet_check_confirmed', true)->pluck('id')->flip();
            $confirmed = $extras->filter(fn (Show $s) => isset($confirmedIds[$s->id]));
            $extras = $extras->reject(fn (Show $s) => isset($confirmedIds[$s->id]));
            foreach ($confirmed as $show) {
                $items[] = [
                    'action' => 'confirmed', 'how' => 'confirmed_by_owner', 'channel_id' => (int) $channelId,
                    'channel' => $channels[$channelId] ?? '#'.$channelId, 'from_channel' => null, 'show_id' => $show->id,
                    'title' => $show->title, 'date' => $show->show_date->toDateString(), 'app_date' => $show->show_date->toDateString(),
                    'changes' => [], 'metrics' => [], 'sheet_orders' => null, 'app_orders' => (int) ($orderRows[$show->id] ?? 0),
                    'raw' => [], 'live_url' => $this->liveUrl($show), 'gross' => (float) $show->gross_revenue,
                ];
            }
            foreach ($extras as $show) {
                $items[] = [
                    'action' => 'extra',
                    'blocked' => $this->deleteBlocker($show),
                    // A second copy of a show that is on Whatnot's list: same
                    // live id, or every one of its orders also on that show.
                    'duplicate_of' => $dup = $this->duplicateOf($show, array_keys($claimed)),
                    // Otherwise sales or orders mean it really aired; it is kept
                    // whatever the exports say (renamed since, or a page missed).
                    'aired' => ! $dup && ((float) $show->gross_revenue > 0 || (int) $show->units_sold > 0 || (int) ($orderRows[$show->id] ?? 0) > 0),
                    'live_url' => $this->liveUrl($show),
                    'how' => 'not_on_whatnot_list',
                    'channel_id' => (int) $channelId,
                    'channel' => $channels[$channelId] ?? '#'.$channelId,
                    'from_channel' => null,
                    'show_id' => $show->id,
                    'title' => $show->title,
                    'date' => $show->show_date->toDateString(),
                    'app_date' => $show->show_date->toDateString(),
                    'changes' => [],
                    'metrics' => [],
                    'sheet_orders' => null,
                    'app_orders' => (int) ($orderRows[$show->id] ?? 0),
                    'raw' => [],
                ];
            }

            // What the app holds for this channel on the export's days, now
            // and once the changes are applied.
            $appNow = Show::where('whatnot_channel_id', $channelId)->whereBetween('show_date', [$cFrom, $cTo])->where('status', '!=', 'cancelled');
            $summary[$channelId] = [
                'name' => $channels[$channelId] ?? '#'.$channelId,
                'from' => $cFrom,
                'to' => $cTo,
                'sheet' => [
                    'shows' => count($rows),
                    'sales' => round(collect($rows)->sum(fn ($r) => (float) ($r['metrics']['gross_revenue'] ?? 0)), 2),
                    'earnings' => round(collect($rows)->sum(fn ($r) => (float) ($r['metrics']['whatnot_net'] ?? 0)), 2),
                    'orders' => (int) collect($rows)->sum(fn ($r) => (int) ($r['orders'] ?? 0)),
                ],
                'app' => [
                    'shows' => (clone $appNow)->count(),
                    'sales' => round((float) (clone $appNow)->sum('gross_revenue'), 2),
                    'earnings' => round((float) (clone $appNow)->sum('whatnot_net'), 2),
                    'orders' => WhatnotShowOrder::whereIn('show_id', (clone $appNow)->select('id'))->count(),
                ],
                // Whatnot lists newest first, so a missing first page shows up as
                // an export that stops early while the app has shows with sales
                // after it.
                'last_row' => $dates->max(),
                'files' => $statsByChannel[$channelId] ?? [],
                'aired_after_last_row' => $dates->isEmpty() ? 0 : Show::where('whatnot_channel_id', $channelId)
                    ->where('show_date', '>', $dates->max())->where('show_date', '<=', $cTo)->where('gross_revenue', '>', 0)->count(),
                'counts' => [
                    'ok' => $mine->where('action', 'ok')->count(),
                    'update' => $mine->where('action', 'update')->count(),
                    'move' => $mine->where('action', 'move')->count(),
                    'create' => $mine->where('action', 'create')->count(),
                    'skip_empty' => $mine->where('action', 'skip_empty')->count(),
                    'extra' => $extras->count(),
                    'confirmed' => $confirmed->count(),
                    'confirmed_sales' => round((float) $confirmed->sum('gross_revenue'), 2),
                    'confirmed_earnings' => round((float) $confirmed->sum('whatnot_net'), 2),
                    'orders_short' => $mine->filter(fn ($it) => $it['sheet_orders'] !== null && $it['app_orders'] < $it['sheet_orders'])->count(),
                ],
            ];
        }

        $order = ['move' => 0, 'create' => 1, 'extra' => 2, 'update' => 3, 'skip_empty' => 4, 'confirmed' => 5, 'ok' => 6];
        usort($items, fn ($a, $b) => [$order[$a['action']], $a['channel'], $b['date'], $a['title']] <=> [$order[$b['action']], $b['channel'], $a['date'], $b['title']]);

        $active = WhatnotChannel::where('status', 'active')->pluck('id')->map(fn ($id) => (int) $id);

        return [
            'channels' => $summary,
            'items' => $items,
            // Deleting what no export lists is only safe when every channel's
            // export is here: otherwise a show filed under the wrong channel
            // whose real channel was not uploaded would look like it never ran.
            'all_channels' => $active->diff(collect(array_keys($sheets))->map(fn ($id) => (int) $id))->isEmpty(),
            'missing_channels' => WhatnotChannel::whereIn('id', $active->diff(collect(array_keys($sheets))->map(fn ($id) => (int) $id)))->pluck('name')->all(),
        ];
    }

    /**
     * The show on Whatnot's list that this one is a copy of: it has the same
     * live id, or every one of its imported orders is also on that show.
     *
     * @param  list<int>  $listedIds  shows that matched an export row
     * @return array{show_id:int,title:string,date:?string,shared:int,orders:int}|null
     */
    private function duplicateOf(Show $show, array $listedIds): ?array
    {
        if ($listedIds === []) {
            return null;
        }

        $liveId = $this->liveIdOf($show);
        if ($liveId) {
            $twin = Show::whereIn('id', $listedIds)->where('id', '!=', $show->id)
                ->where(fn ($q) => $q->where('whatnot_show_id', $liveId)->orWhere('detail_url', 'like', '%'.$liveId.'%'))
                ->first(['id', 'title', 'show_date']);
            if ($twin) {
                return ['show_id' => $twin->id, 'title' => $twin->title, 'date' => $twin->show_date?->toDateString(), 'shared' => 0, 'orders' => 0, 'by' => 'live_id'];
            }
        }

        $orderIds = WhatnotShowOrder::where('show_id', $show->id)->whereNotNull('whatnot_order_id')->pluck('whatnot_order_id')->unique();
        if ($orderIds->isNotEmpty()) {
            $best = WhatnotShowOrder::whereIn('whatnot_order_id', $orderIds)->whereIn('show_id', $listedIds)->where('show_id', '!=', $show->id)
                ->selectRaw('show_id, COUNT(DISTINCT whatnot_order_id) as n')->groupBy('show_id')->orderByDesc('n')->first();
            if ($best && (int) $best->n >= $orderIds->count()) {
                $twin = Show::find($best->show_id, ['id', 'title', 'show_date']);

                return ['show_id' => $twin->id, 'title' => $twin->title, 'date' => $twin->show_date?->toDateString(), 'shared' => (int) $best->n, 'orders' => $orderIds->count(), 'by' => 'orders'];
            }
        }

        // Every package it shipped (by tracking number) also shipped from a
        // show Whatnot does list.
        $tracking = \App\Models\Shipment::where('show_id', $show->id)->whereNotNull('tracking_number')->where('tracking_number', '!=', '')
            ->pluck('tracking_number')->unique();
        if ($tracking->isNotEmpty()) {
            $best = \App\Models\Shipment::whereIn('tracking_number', $tracking)->whereIn('show_id', $listedIds)->where('show_id', '!=', $show->id)
                ->selectRaw('show_id, COUNT(DISTINCT tracking_number) as n')->groupBy('show_id')->orderByDesc('n')->first();
            if ($best && (int) $best->n >= $tracking->count()) {
                $twin = Show::find($best->show_id, ['id', 'title', 'show_date']);

                return ['show_id' => $twin->id, 'title' => $twin->title, 'date' => $twin->show_date?->toDateString(), 'shared' => (int) $best->n, 'orders' => $tracking->count(), 'by' => 'shipments'];
            }
        }

        // The same title within a day of a show Whatnot does list, on any
        // channel. Had both aired, Whatnot would list both.
        if ($show->show_date) {
            $key = $this->csv->normalize($show->title);
            $twin = Show::whereIn('id', $listedIds)->where('id', '!=', $show->id)
                ->whereBetween('show_date', [$show->show_date->copy()->subDay()->toDateString(), $show->show_date->copy()->addDay()->toDateString()])
                ->get(['id', 'title', 'show_date'])
                ->first(fn (Show $s) => $key !== '' && $this->csv->normalize($s->title) === $key);
            if ($twin) {
                return ['show_id' => $twin->id, 'title' => $twin->title, 'date' => $twin->show_date?->toDateString(), 'shared' => 0, 'orders' => $orderIds->count(), 'by' => 'title'];
            }
        }

        return null;
    }

    /** Mark (or unmark) a show the exports do not list as checked and real. */
    public function confirmReal(int $showId, bool $confirmed = true): void
    {
        $show = Show::findOrFail($showId);
        $raw = is_array($show->raw_import_payload) ? $show->raw_import_payload : [];
        if ($confirmed) {
            $raw['_sheet_check_confirmed'] = true;
            $raw['_sheet_check_confirmed_at'] = now()->toIso8601String();
            $raw['_sheet_check_confirmed_by'] = auth()->id();
        } else {
            unset($raw['_sheet_check_confirmed'], $raw['_sheet_check_confirmed_at'], $raw['_sheet_check_confirmed_by']);
        }
        $show->forceFill(['raw_import_payload' => $raw])->save();
    }

    /**
     * Delete one show the exports do not list, whatever it holds — for the
     * owner deciding a leftover by hand. Its draft and paid pay go with it.
     */
    public function deleteUnlisted(int $showId, int $channelId): bool
    {
        $show = Show::find($showId);
        if (! $show) {
            return false;
        }

        DB::transaction(function () use ($show, $channelId) {
            $batches = Payout::where('show_id', $show->id)->whereNotNull('weekly_payout_batch_id')->pluck('weekly_payout_batch_id')->unique();
            Payout::where('show_id', $show->id)->delete();
            \App\Models\WeeklyPayoutBatch::whereKey($batches)->get()->each->recalculateTotal();

            $activity = activity('show')->withProperties([
                'title' => $show->title, 'show_date' => $show->show_date?->toDateString(), 'channel_id' => $channelId,
                'reason' => 'Deleted by the owner from Whatnot Sheet Check: not on any Whatnot export',
            ]);
            if ($user = auth()->user()) {
                $activity->causedBy($user);
            }
            $activity->log('Show deleted: not on Whatnot\'s list (chosen by hand)');

            $show->delete();
        });

        return true;
    }

    private function liveIdOf(Show $show): ?string
    {
        $raw = Show::whereKey($show->id)->value('detail_url');
        $id = Show::whereKey($show->id)->value('whatnot_show_id')
            ?: (preg_match('/([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})/i', (string) $raw, $m) ? $m[1] : null);

        return $id ? strtolower($id) : null;
    }

    private function liveUrl(Show $show): ?string
    {
        $id = $this->liveIdOf($show);

        return $id ? 'https://www.whatnot.com/live/'.$id : null;
    }

    /** Why a show the exports do not list must not be deleted automatically, or null. */
    private function deleteBlocker(Show $show): ?string
    {
        if (Payout::where('show_id', $show->id)->where(fn ($q) => $q->where('status', '!=', 'draft')
            ->orWhereHas('batch', fn ($b) => $b->where('status', '!=', 'draft')))->exists()) {
            return 'Has finalized or paid pay';
        }
        if (\App\Models\StreamerLogEntry::where('show_id', $show->id)->where('status', 'admin_approved')->exists()) {
            return 'Has an approved show report';
        }

        return null;
    }

    /**
     * Carry out a plan: move, create, and fill in figures.
     *
     * @return array{moved:int,created:int,updated:int,failed:array<string,string>}
     */
    public function apply(array $plan, bool $deleteExtras = false, bool $includePaid = false): array
    {
        $totals = ['moved' => 0, 'created' => 0, 'updated' => 0, 'deleted' => 0, 'failed' => []];
        $deleteExtras = $deleteExtras && ($plan['all_channels'] ?? false);

        foreach ($plan['items'] as $item) {
            if ($item['action'] === 'extra') {
                // Imported orders mean the show really aired (most likely renamed
                // on Whatnot since), so it is never deleted from here.
                $removable = ! empty($item['duplicate_of']) || (empty($item['aired']) && ($item['app_orders'] ?? 0) === 0);
                if ($deleteExtras && $removable && ($includePaid || empty($item['blocked']))) {
                    try {
                        DB::transaction(fn () => $this->deleteShow($item, $includePaid));
                        $totals['deleted']++;
                    } catch (\Throwable $e) {
                        report($e);
                        $totals['failed'][$item['title'].' ('.$item['date'].')'] = \Illuminate\Support\Str::limit($e->getMessage(), 160);
                    }
                }
                continue;
            }
            if (! in_array($item['action'], ['move', 'update', 'create'], true)) {
                continue;
            }

            try {
                DB::transaction(function () use ($item, &$totals) {
                    if ($item['action'] === 'create') {
                        $show = $this->create($item);
                        $totals['created']++;
                        $this->log($show, $item, 'whatnot_sheet_show_created', []);

                        return;
                    }

                    $show = Show::lockForUpdate()->findOrFail($item['show_id']);
                    $fields = [];
                    if ((int) $show->whatnot_channel_id !== $item['channel_id']) {
                        $fields['whatnot_channel_id'] = $item['channel_id'];
                        $fields['channel_attribution_suspect'] = false;
                    }
                    $changes = $this->changes($show, $item['metrics']);
                    foreach ($changes as $field => [, $after]) {
                        $fields[$field] = $after;
                    }
                    if ($fields === []) {
                        return;
                    }

                    $raw = is_array($show->raw_import_payload) ? $show->raw_import_payload : [];
                    $raw['_analytics_csv'] = $item['raw'];
                    $raw['_analytics_csv_imported_at'] = now()->toIso8601String();
                    if (isset($fields['whatnot_channel_id'])) {
                        $raw['_sheet_check_moved_from'] = $show->whatnot_channel_id;
                    }

                    $status = $this->csv->complete(array_merge($show->only(['gross_revenue', 'whatnot_net', 'show_duration', 'completed_earnings']), $item['metrics'])) ? 'complete' : 'partial';
                    $show->trackChanges(array_intersect_key($fields, array_flip(['whatnot_channel_id', 'gross_revenue', 'whatnot_net', 'units_sold', 'show_duration'])), 'whatnot_sheet');
                    $show->forceFill($fields + [
                        'raw_import_payload' => $raw,
                        'analytics_sync_status' => $status,
                        'analytics_sync_note' => 'Figures from the Whatnot Shows export (Whatnot Sheet Check).',
                        'last_analytics_synced_at' => now(),
                        'analytics_unavailable_at' => null,
                    ])->save();

                    if (isset($fields['whatnot_channel_id'])) {
                        Payout::where('show_id', $show->id)->update(['whatnot_channel_id' => $item['channel_id']]);
                        $totals['moved']++;
                    }
                    if ($changes) {
                        $totals['updated']++;
                    }
                    $this->log($show, $item, isset($fields['whatnot_channel_id']) ? 'whatnot_sheet_channel_moved' : 'whatnot_sheet_figures', $changes);
                });
            } catch (\Throwable $e) {
                report($e);
                $totals['failed'][$item['title'].' ('.$item['date'].')'] = \Illuminate\Support\Str::limit($e->getMessage(), 160);
            }
        }

        return $totals;
    }

    /**
     * Pair a day's rows with that day's shows of the same title. One of each
     * is simply a match; otherwise the closest figures pair up first.
     *
     * @return list<array{0: array, 1: ?Show}>
     */
    private function pair(Collection $rows, Collection $shows): array
    {
        $rows = $rows->values()->all();
        $shows = $shows->values()->all();
        $out = [];
        $options = [];
        foreach ($rows as $ri => $row) {
            foreach ($shows as $si => $show) {
                $options[] = [$this->distance($row, $show), $ri, $si];
            }
        }
        usort($options, fn ($a, $b) => $a[0] <=> $b[0]);

        $usedRows = [];
        $usedShows = [];
        foreach ($options as [, $ri, $si]) {
            if (isset($usedRows[$ri]) || isset($usedShows[$si])) {
                continue;
            }
            $usedRows[$ri] = $usedShows[$si] = true;
            $out[] = [$rows[$ri], $shows[$si]];
        }
        foreach ($rows as $ri => $row) {
            if (! isset($usedRows[$ri])) {
                $out[] = [$row, null];
            }
        }

        return $out;
    }

    /** How far apart a row and a show's figures are (0 = identical). */
    private function distance(array $row, Show $show): float
    {
        $d = 0.0;
        foreach (['gross_revenue', 'show_duration', 'buyers_count'] as $field) {
            $a = $row['metrics'][$field] ?? null;
            $b = $show->{$field};
            if ($a === null || $b === null) {
                $d += 0.5;
                continue;
            }
            $d += abs((float) $a - (float) $b) / max(1.0, abs((float) $a), abs((float) $b));
        }

        return $d;
    }

    /** @return array<string, array{0: mixed, 1: mixed}> field => [app, sheet] */
    private function changes(Show $show, array $metrics): array
    {
        $changes = [];
        foreach ($metrics as $field => $value) {
            $current = $show->{$field};
            // The export rounds money to whole dollars; existing cents within
            // that rounding are more precise, not different.
            if (in_array($field, ['gross_revenue', 'whatnot_net', 'avg_order_value', 'giveaway_spend'], true)
                && $current !== null && abs((float) $current - (float) $value) < 0.5) {
                continue;
            }
            if ($field === 'show_duration' && (int) $value === 0 && (int) $current > 0) {
                continue;
            }
            if ($this->csv->different($current, $value)) {
                $changes[$field] = [$current === null ? null : (is_numeric($current) ? (float) $current : $current), $value];
            }
        }

        return $changes;
    }

    /** Remove a show none of the exports list, with its draft pay. */
    private function deleteShow(array $item, bool $includePaid = false): void
    {
        $show = Show::lockForUpdate()->find($item['show_id']);
        if (! $show || (! $includePaid && $this->deleteBlocker($show))) {
            return;
        }
        // Re-check on the record as it is now: a duplicate must still be one,
        // and anything else must still show no sign of having aired.
        $duplicate = ! empty($item['duplicate_of']) && $this->duplicateOf($show, [$item['duplicate_of']['show_id']]) !== null;
        if (! $duplicate && (WhatnotShowOrder::where('show_id', $show->id)->exists() || (float) $show->gross_revenue > 0 || (int) $show->units_sold > 0)) {
            return;
        }

        // Before payroll goes live, pay on a show that never ran goes with it,
        // whatever its status.
        $batches = Payout::where('show_id', $show->id)->whereNotNull('weekly_payout_batch_id')->pluck('weekly_payout_batch_id')->unique();
        Payout::where('show_id', $show->id)->when(! $includePaid, fn ($q) => $q->where('status', 'draft'))->delete();
        \App\Models\WeeklyPayoutBatch::whereKey($batches)->get()->each->recalculateTotal();

        $activity = activity('show')->withProperties([
            'title' => $show->title, 'show_date' => $show->show_date?->toDateString(), 'channel_id' => $show->whatnot_channel_id,
            'reason' => 'Not on the Whatnot Shows export for its channel (Whatnot Sheet Check)',
        ]);
        if ($user = auth()->user()) {
            $activity->causedBy($user);
        }
        $activity->log('Show deleted: not on Whatnot\'s list');

        $show->delete();
    }

    private function create(array $item): Show
    {
        $show = new Show;
        $show->forceFill($item['metrics'] + [
            'whatnot_channel_id' => $item['channel_id'],
            'title' => $item['title'],
            'show_date' => $item['date'],
            'status' => 'draft',
            'import_source' => 'auto_whatnot',
            'created_by' => auth()->id(),
            'analytics_sync_status' => $this->csv->complete($item['metrics']) ? 'complete' : 'partial',
            'analytics_sync_note' => 'Created from the Whatnot Shows export (Whatnot Sheet Check).',
            'last_analytics_synced_at' => now(),
            'raw_import_payload' => ['source' => 'whatnot_analytics_csv', '_analytics_csv' => $item['raw'], '_analytics_csv_imported_at' => now()->toIso8601String()],
        ])->save();
        $show->detectStreamers();

        return $show;
    }

    private function log(Show $show, array $item, string $event, array $changes): void
    {
        ShowIngestionLog::create([
            'show_id' => $show->id,
            'whatnot_channel_id' => $item['channel_id'],
            'source' => 'whatnot_analytics_csv',
            'status' => 'success',
            'raw_payload' => ['event' => $event, 'from_channel' => $item['from_channel'], 'changed_fields' => $changes, 'csv' => $item['raw']],
        ]);
    }
}
