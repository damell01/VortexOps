<?php

namespace App\Console\Commands;

use App\Models\Show;
use App\Models\WhatnotChannel;
use App\Services\WhatnotDataNormalizer;
use App\Services\WhatnotScraper;
use App\Support\WhatnotPipelineLock;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class BackfillMissingWhatnotAnalytics extends Command
{
    protected $signature = 'whatnot:backfill-missing-analytics
        {--channel= : Channel name, username, or ID}
        {--days=14 : How far back to look for completed shows}
        {--limit=8 : Maximum shows to backfill per run}
        {--skip-if-busy : Skip cleanly if another Whatnot pipeline is active}';

    protected $description = 'Backfill Gross Revenue and Estimated Net Earnings for completed shows by seeding Whatnot analytics with each show UUID individually.';

    public function handle(WhatnotScraper $scraper, WhatnotDataNormalizer $normalizer): int
    {
        $lock = WhatnotPipelineLock::acquire(
            'Missing show analytics backfill',
            $this->option('skip-if-busy') ? 0 : 7200,
        );

        if (! $lock) {
            $message = WhatnotPipelineLock::busyMessage();
            if ($this->option('skip-if-busy')) {
                $this->line("Missing show analytics backfill: skipped — {$message}");
                return self::SUCCESS;
            }
            $this->error($message);
            return self::FAILURE;
        }

        try {
            return $this->runBackfill($scraper, $normalizer);
        } finally {
            WhatnotPipelineLock::release($lock);
        }
    }

    private function runBackfill(WhatnotScraper $scraper, WhatnotDataNormalizer $normalizer): int
    {
        $days = max(1, min(90, (int) $this->option('days')));
        $limit = max(1, min(25, (int) $this->option('limit')));

        $query = Show::query()
            ->with('channel')
            ->whereDate('show_date', '<=', today())
            ->whereDate('show_date', '>=', today()->subDays($days))
            ->whereNotIn('status', ['cancelled'])
            ->where(function ($q) {
                $q->whereNull('gross_revenue')->orWhere('gross_revenue', '<=', 0)
                  ->orWhereNull('whatnot_net')->orWhere('whatnot_net', '<=', 0);
            })
            ->orderByDesc('show_date')
            ->orderByDesc('id');

        if ($channelOpt = trim((string) $this->option('channel'))) {
            $channel = is_numeric($channelOpt)
                ? WhatnotChannel::find((int) $channelOpt)
                : WhatnotChannel::where('name', $channelOpt)
                    ->orWhere('whatnot_username', ltrim($channelOpt, '@'))
                    ->first();

            if (! $channel) {
                $this->error("Channel not found: {$channelOpt}");
                return self::FAILURE;
            }

            $query->where('whatnot_channel_id', $channel->id);
        }

        $shows = $query->limit($limit)->get();
        if ($shows->isEmpty()) {
            $this->info('No completed shows with missing Whatnot analytics were found.');
            return self::SUCCESS;
        }

        $this->info("Backfilling {$shows->count()} show(s) one UUID at a time…");
        $updated = 0;
        $failed = 0;

        foreach ($shows as $show) {
            $liveId = $this->resolveLiveId($show);
            if (! $liveId) {
                $this->warn("Show #{$show->id}: no Whatnot UUID in whatnot_show_id, detail_url, or stored import payload; skipped.");
                $failed++;
                continue;
            }

            $channelUsername = $show->channel?->whatnot_username;
            $this->line("  #{$show->id} {$show->show_date?->format('Y-m-d')} {$show->title}");

            try {
                $rawRows = $scraper->fetchShows(
                    limit: 1,
                    debug: false,
                    channelUsername: $channelUsername,
                    seedLiveId: $liveId,
                );

                $raw = collect($rawRows)->first(function (array $row) use ($liveId) {
                    $candidate = strtolower((string) ($row['whatnot_live_id'] ?? $row['live_id'] ?? ''));
                    return $candidate === '' || $candidate === strtolower($liveId);
                });

                if (! is_array($raw)) {
                    throw new \RuntimeException('Whatnot returned no analytics row for this UUID.');
                }

                $row = $normalizer->normalizeShow($raw);
                $fields = [];
                foreach ([
                    'gross_revenue', 'whatnot_net', 'completed_earnings', 'avg_order_value',
                    'giveaway_spend', 'units_sold', 'giveaways_count', 'buyers_count',
                    'first_time_buyers', 'returning_buyers', 'shares_count',
                    'max_concurrent_viewers', 'total_views', 'show_duration',
                ] as $field) {
                    if (($row[$field] ?? null) !== null) {
                        $fields[$field] = $row[$field];
                    }
                }

                if ($fields === []) {
                    throw new \RuntimeException('Analytics page loaded but no usable metrics were extracted.');
                }

                $fields['whatnot_show_id'] = $show->whatnot_show_id ?: $liveId;
                $fields['last_synced_at'] = now();
                $fields['raw_import_payload'] = $raw;
                $show->forceFill($fields)->save();
                $updated++;

                $gross = $fields['gross_revenue'] ?? $show->gross_revenue;
                $net = $fields['whatnot_net'] ?? $show->whatnot_net;
                $this->info('    updated: gross $'.number_format((float) $gross, 2).' · est. net $'.number_format((float) $net, 2));
            } catch (\Throwable $e) {
                $failed++;
                $this->error("    failed: {$e->getMessage()}");
                Log::warning('Missing Whatnot analytics backfill failed', [
                    'show_id' => $show->id,
                    'live_id' => $liveId,
                    'channel' => $channelUsername,
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        $this->newLine();
        $this->info("Analytics backfill complete: {$updated} updated, {$failed} failed.");
        return $failed > 0 && $updated === 0 ? self::FAILURE : self::SUCCESS;
    }

    private function resolveLiveId(Show $show): ?string
    {
        foreach ([$show->whatnot_show_id, $show->detail_url] as $candidate) {
            if ($liveId = $this->extractLiveId((string) $candidate)) {
                return $liveId;
            }
        }

        return $this->findLiveIdInPayload($show->raw_import_payload);
    }

    private function findLiveIdInPayload(mixed $value): ?string
    {
        if (is_string($value)) {
            return $this->extractLiveId($value);
        }

        if (! is_array($value)) {
            return null;
        }

        // Prefer fields that are expected to identify the livestream before
        // scanning the rest of an old payload for embedded URLs/IDs.
        foreach (['whatnot_live_id', 'live_id', 'whatnot_show_id', 'show_id', 'detail_url', 'url', 'href'] as $key) {
            if (array_key_exists($key, $value) && ($liveId = $this->findLiveIdInPayload($value[$key]))) {
                return $liveId;
            }
        }

        foreach ($value as $item) {
            if ($liveId = $this->findLiveIdInPayload($item)) {
                return $liveId;
            }
        }

        return null;
    }

    private function extractLiveId(string $value): ?string
    {
        return preg_match('/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i', $value, $m)
            ? strtolower($m[0])
            : null;
    }
}
