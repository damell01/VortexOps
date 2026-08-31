<?php

namespace App\Services;

use App\Models\Show;
use App\Models\ShowIngestionLog;
use App\Models\StreamerLogEntry;
use App\Models\WhatnotChannel;
use App\Models\WhatnotLedgerEntry;
use App\Models\WhatnotSync;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WhatnotDesktopIngestor
{
    public function __construct(private readonly WhatnotScraper $scraper)
    {
    }

    /**
     * Persist one verified channel bundle collected by the desktop agent.
     *
     * The desktop process never chooses a numeric channel id. We resolve the
     * verified Whatnot username here and refuse a requested/verified mismatch
     * before touching any business data.
     *
     * @return array<string,mixed>
     */
    public function ingest(array $bundle): array
    {
        $requested = trim((string) ($bundle['requested_channel_username'] ?? ''));
        $verified  = trim((string) ($bundle['verified_channel_username'] ?? ''));

        if ($requested === '' || $verified === '') {
            throw new \InvalidArgumentException('Both requested_channel_username and verified_channel_username are required.');
        }

        if ($this->normalizeUsername($requested) !== $this->normalizeUsername($verified)) {
            throw new \RuntimeException(
                "CHANNEL_CONTEXT_MISMATCH: desktop collector requested=@{$requested} verified=@{$verified}"
            );
        }

        $channel = WhatnotChannel::query()
            ->get()
            ->first(fn (WhatnotChannel $candidate) =>
                $this->normalizeUsername($candidate->whatnot_username) === $this->normalizeUsername($verified)
            );

        if (! $channel) {
            throw new \RuntimeException("Unknown Whatnot channel @{$verified}; add it in VortexOps before importing.");
        }

        if ($channel->status !== 'active') {
            throw new \RuntimeException("Whatnot channel @{$verified} is not active in VortexOps.");
        }

        $sync = WhatnotSync::create([
            'whatnot_channel_id' => $channel->id,
            'type'               => 'desktop_collector',
            'status'             => 'running',
            'started_at'         => now(),
            'summary'            => [
                'collector_run_id' => $bundle['collector_run_id'] ?? null,
                'collector_version' => $bundle['collector_version'] ?? null,
                'computer_name' => $bundle['computer_name'] ?? null,
            ],
        ]);

        try {
            $result = DB::transaction(function () use ($bundle, $channel) {
                $showResult = $this->persistShows($channel, (array) ($bundle['shows'] ?? []));
                $showMap = $showResult['show_map'];

                $orderStats = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'unmatched_shows' => 0];
                foreach ((array) ($bundle['orders_by_live_id'] ?? []) as $liveId => $rows) {
                    $show = $showMap[(string) $liveId] ?? $this->findShowByLiveId($channel, (string) $liveId);
                    if (! $show) {
                        $orderStats['unmatched_shows']++;
                        continue;
                    }

                    $rows = is_array($rows) ? $rows : [];
                    $expected = (int) ($show->units_sold ?? 0);
                    if ($expected > 0 && count($rows) > ($expected * 2 + 100)) {
                        throw new \RuntimeException(
                            "ORDER_SCOPE_SAFETY_REJECTED: show #{$show->id} expected about {$expected} units but collector returned " . count($rows) . ' rows.'
                        );
                    }

                    $stats = $this->scraper->persistShowOrders($show, $rows);
                    foreach (['created', 'updated', 'skipped'] as $key) {
                        $orderStats[$key] += (int) ($stats[$key] ?? 0);
                    }
                }

                $shipmentStats = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'unmatched_shows' => 0];
                foreach ((array) ($bundle['shipments_by_live_id'] ?? []) as $liveId => $rows) {
                    $show = $showMap[(string) $liveId] ?? $this->findShowByLiveId($channel, (string) $liveId);
                    if (! $show) {
                        $shipmentStats['unmatched_shows']++;
                        continue;
                    }

                    $rows = is_array($rows) ? $rows : [];

                    // Shipment rows also carry order-level weight/carrier/status.
                    // Merge those fields onto existing order rows before creating
                    // the Shipment records used by fulfillment views.
                    $orderMerge = $this->scraper->persistShowOrders($show, $rows);
                    $shipment = $this->scraper->persistShipments($show, $rows);
                    $shipmentStats['created'] += (int) ($shipment['created'] ?? 0);
                    $shipmentStats['updated'] += (int) ($shipment['updated'] ?? 0) + (int) ($orderMerge['updated'] ?? 0);
                    $shipmentStats['skipped'] += (int) ($shipment['skipped'] ?? 0);
                }

                $ledgerStats = ['created' => 0, 'skipped' => 0];
                foreach ((array) ($bundle['ledger'] ?? []) as $row) {
                    if (! is_array($row)) {
                        $ledgerStats['skipped']++;
                        continue;
                    }
                    if ($this->persistLedgerRow($channel, $row)) {
                        $ledgerStats['created']++;
                    } else {
                        $ledgerStats['skipped']++;
                    }
                }

                return [
                    'channel' => [
                        'id' => $channel->id,
                        'name' => $channel->name,
                        'whatnot_username' => $channel->whatnot_username,
                    ],
                    'shows' => [
                        'created' => $showResult['created'],
                        'updated' => $showResult['updated'],
                        'skipped' => $showResult['skipped'],
                    ],
                    'orders' => $orderStats,
                    'shipments' => $shipmentStats,
                    'ledger' => $ledgerStats,
                    'component_status' => (array) ($bundle['component_status'] ?? []),
                ];
            });

            $sync->markCompleted([
                'shows_created'  => $result['shows']['created'],
                'shows_updated'  => $result['shows']['updated'],
                'orders_created' => $result['orders']['created'],
                'orders_updated' => $result['orders']['updated'],
                'error_count'    => count(array_filter(
                    $result['component_status'],
                    fn ($status) => is_array($status) && (($status['ok'] ?? true) === false)
                )),
                'summary'        => array_merge($sync->summary ?? [], $result),
            ]);

            return array_merge(['sync_id' => $sync->id], $result);
        } catch (\Throwable $e) {
            $sync->markFailed($e);
            throw $e;
        }
    }

    /** @return array{created:int,updated:int,skipped:int,show_map:array<string,Show>} */
    private function persistShows(WhatnotChannel $channel, array $rows): array
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $showMap = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                $skipped++;
                continue;
            }

            $title = trim((string) ($row['title'] ?? '')) ?: null;
            $date = $this->parseShowDate($row['show_date'] ?? $row['show_date_raw'] ?? null);
            $liveId = $this->extractLiveId($row['whatnot_live_id'] ?? $row['whatnot_show_id'] ?? $row['detail_url'] ?? null);
            $detailUrl = $row['detail_url'] ?? ($liveId ? "https://www.whatnot.com/dashboard/live/{$liveId}" : null);

            if (! $title && ! $date && ! $liveId) {
                $skipped++;
                ShowIngestionLog::create([
                    'whatnot_channel_id' => $channel->id,
                    'source' => 'whatnot_desktop',
                    'status' => 'failed',
                    'error_message' => 'Desktop collector row had no title, date, or livestream id.',
                    'raw_payload' => $row,
                ]);
                continue;
            }

            $show = null;
            if ($liveId) {
                $show = Show::query()
                    ->where('whatnot_channel_id', $channel->id)
                    ->where(function ($query) use ($liveId, $detailUrl) {
                        $query->where('whatnot_show_id', $liveId);
                        if ($detailUrl) {
                            $query->orWhere('detail_url', $detailUrl);
                        }
                    })
                    ->first();
            }

            if (! $show && $title && $date) {
                $show = Show::query()
                    ->where('whatnot_channel_id', $channel->id)
                    ->where('title', $title)
                    ->whereDate('show_date', $date)
                    ->first();
            }

            $payload = array_filter([
                'whatnot_channel_id' => $channel->id,
                'whatnot_show_id' => $liveId,
                'title' => $title,
                'show_date' => $date,
                'start_time' => $row['start_time'] ?? null,
                'end_time' => $row['end_time'] ?? null,
                'show_duration' => $row['show_duration'] ?? null,
                'gross_revenue' => $row['gross_revenue'] ?? null,
                'whatnot_net' => $row['whatnot_net'] ?? null,
                'whatnot_fees' => $row['whatnot_fees'] ?? null,
                'whatnot_payout_amount' => $row['whatnot_payout_amount'] ?? null,
                'tips' => $row['tips'] ?? null,
                'units_sold' => $row['units_sold'] ?? null,
                'detail_url' => $detailUrl,
                'cover_image_url' => $row['cover_image_url'] ?? null,
                'completed_earnings' => $row['completed_earnings'] ?? null,
                'avg_order_value' => $row['avg_order_value'] ?? null,
                'giveaway_spend' => $row['giveaway_spend'] ?? null,
                'giveaways_count' => $row['giveaways_count'] ?? null,
                'buyers_count' => $row['buyers_count'] ?? null,
                'first_time_buyers' => $row['first_time_buyers'] ?? null,
                'returning_buyers' => $row['returning_buyers'] ?? null,
                'shares_count' => $row['shares_count'] ?? null,
                'max_concurrent_viewers' => $row['max_concurrent_viewers'] ?? null,
                'total_views' => $row['total_views'] ?? null,
                'avg_order_rating' => $row['avg_order_rating'] ?? null,
                'last_synced_at' => now(),
                'import_source' => 'auto_whatnot',
                'raw_import_payload' => $row,
            ], fn ($value) => $value !== null && $value !== '');

            if ($show) {
                // Never let the collector rewrite attribution. A show located by
                // this channel stays on this channel; a mismatched numeric id is
                // never accepted from the desktop payload in the first place.
                unset($payload['whatnot_channel_id'], $payload['import_source']);

                if (in_array($show->status, ['pending_approval', 'reconciled', 'closed'], true)) {
                    $changes = [];
                    foreach (['gross_revenue', 'whatnot_net', 'tips', 'units_sold'] as $field) {
                        if (! array_key_exists($field, $payload)) {
                            continue;
                        }
                        $old = (float) $show->{$field};
                        $new = (float) $payload[$field];
                        if (abs($old - $new) > 0.01) {
                            $changes[] = "{$field}: {$old} → {$new}";
                        }
                    }
                    if ($changes !== []) {
                        $payload['financials_revised_after_lock'] = true;
                        $payload['revision_notes'] = trim(
                            ($show->revision_notes ? $show->revision_notes . "\n" : '')
                            . now()->format('M j, Y g:ia') . ' — desktop collector — ' . implode('; ', $changes)
                        );
                    }
                }

                $dirty = array_filter(
                    $payload,
                    fn ($value, $key) => $show->getAttribute($key) != $value,
                    ARRAY_FILTER_USE_BOTH
                );

                if ($dirty !== []) {
                    $show->trackChanges($dirty, 'whatnot_desktop_import');
                    $show->update($dirty);
                    $updated++;
                } else {
                    $skipped++;
                }
            } else {
                if (! $date) {
                    $skipped++;
                    ShowIngestionLog::create([
                        'whatnot_channel_id' => $channel->id,
                        'source' => 'whatnot_desktop',
                        'status' => 'failed',
                        'error_message' => 'Desktop collector could not create a show without show_date.',
                        'raw_payload' => $row,
                    ]);
                    continue;
                }

                $show = Show::create(array_merge($payload, [
                    'status' => ((int) ($payload['units_sold'] ?? 0)) > 0 ? 'mapping' : 'draft',
                    'created_by' => 1,
                ]));
                $created++;
            }

            if ($show->streamers()->count() === 0) {
                $show->detectStreamers();
            }
            $this->ensureStreamerLogEntry($show);

            ShowIngestionLog::create([
                'show_id' => $show->id,
                'whatnot_channel_id' => $channel->id,
                'source' => 'whatnot_desktop',
                'status' => 'success',
                'raw_payload' => $row,
            ]);

            if ($liveId) {
                $showMap[$liveId] = $show;
            }
        }

        return compact('created', 'updated', 'skipped', 'showMap') + ['show_map' => $showMap];
    }

    private function ensureStreamerLogEntry(Show $show): void
    {
        $show->loadMissing('streamers');
        $streamer = $show->primaryStreamer();
        if (! $streamer) {
            return;
        }

        StreamerLogEntry::firstOrCreate(
            ['show_id' => $show->id],
            ['streamer_id' => $streamer->id, 'status' => 'pending', 'gross_revenue' => $show->gross_revenue],
        );
    }

    private function findShowByLiveId(WhatnotChannel $channel, string $liveId): ?Show
    {
        $liveId = $this->extractLiveId($liveId);
        if (! $liveId) {
            return null;
        }

        return Show::query()
            ->where('whatnot_channel_id', $channel->id)
            ->where(function ($query) use ($liveId) {
                $query->where('whatnot_show_id', $liveId)
                    ->orWhere('detail_url', 'like', '%' . $liveId . '%');
            })
            ->first();
    }

    private function persistLedgerRow(WhatnotChannel $channel, array $row): bool
    {
        $amountRaw = $row['amount'] ?? null;
        $amount = ($amountRaw !== null && $amountRaw !== '')
            ? (float) str_replace(['$', ',', ' '], '', (string) $amountRaw)
            : null;

        $dedup = md5(implode('|', [
            $channel->id,
            $row['order_id'] ?? '',
            $row['listing_id'] ?? '',
            $row['created_date'] ?? '',
            $amountRaw ?? '',
            $row['transaction_type'] ?? '',
        ]));

        if (WhatnotLedgerEntry::where('dedup_key', $dedup)->exists()) {
            return false;
        }

        WhatnotLedgerEntry::create([
            'whatnot_channel_id' => $channel->id,
            'created_date' => $this->parseDateTime($row['created_date'] ?? null),
            'completed_date' => $this->parseDateTime($row['completed_date'] ?? null),
            'amount' => $amount,
            'listing_id' => $row['listing_id'] ?? null,
            'whatnot_order_id' => $row['order_id'] ?? null,
            'order_hash' => $row['order_hash'] ?? null,
            'message' => $row['message'] ?? null,
            'status' => $row['status'] ?? null,
            'transaction_type' => $row['transaction_type'] ?? null,
            'dedup_key' => $dedup,
            'raw_data' => $row,
        ]);

        return true;
    }

    private function parseShowDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            if (is_numeric($value)) {
                $number = (float) $value;
                return Carbon::createFromTimestampMs($number < 1e11 ? $number * 1000 : $number)->toDateString();
            }
            return Carbon::parse((string) $value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseDateTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            return Carbon::parse((string) $value)->toDateTimeString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function extractLiveId(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }
        if (preg_match('/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i', (string) $value, $match)) {
            return strtolower($match[0]);
        }
        return null;
    }

    private function normalizeUsername(?string $username): string
    {
        return strtolower(preg_replace('/[^a-z0-9]/i', '', (string) $username));
    }
}
