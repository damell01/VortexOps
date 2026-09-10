<?php

namespace App\Console\Commands;

use App\Models\Show;
use App\Models\WhatnotChannel;
use App\Services\WhatnotDataNormalizer;
use App\Services\WhatnotScraper;
use App\Support\WhatnotPipelineLock;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class BackfillMissingWhatnotAnalytics extends Command
{
    protected $signature = 'whatnot:backfill-missing-analytics
        {--channel= : Channel name, username, or ID}
        {--days=90 : How far back to look for completed shows}
        {--limit=500 : Maximum shows to backfill per run}
        {--quarantined-only : Only retry shows currently carrying _analytics_quarantine}
        {--skip-if-busy : Skip cleanly if another Whatnot pipeline is active}';

    protected $description = 'Backfill Gross Revenue and Estimated Net Earnings for completed shows by seeding Whatnot analytics with each show UUID individually.';

    /** @var array<int,array<string,mixed>>|null */
    private ?array $discoveredSellerShows = null;

    private bool $sellerDiscoveryAttempted = false;

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
        $days = max(1, min(3650, (int) $this->option('days')));
        $limit = max(1, min(500, (int) $this->option('limit')));

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

        if ($this->option('quarantined-only')) {
            $query->whereRaw("JSON_EXTRACT(raw_import_payload, '$._analytics_quarantine') IS NOT NULL");
        }

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
            $this->info($this->option('quarantined-only')
                ? 'No quarantined shows needing Whatnot analytics were found.'
                : 'No completed shows with missing Whatnot analytics were found.');
            return self::SUCCESS;
        }

        $scope = $this->option('quarantined-only') ? 'quarantined ' : '';
        $this->info("Backfilling {$shows->count()} {$scope}show(s) one UUID at a time…");
        $updated = 0;
        $failed = 0;

        foreach ($shows as $show) {
            $liveId = $this->resolveLiveId($show, $scraper);
            if (! $liveId) {
                $this->warn("Show #{$show->id}: UUID still unresolved after stored-data and seller-show discovery; skipped.");
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
                    expectedTitle: (string) $show->title,
                    expectedDate: $show->show_date?->format('Y-m-d'),
                );

                $raw = collect($rawRows)->first(function (array $row) use ($show, $liveId) {
                    return $this->analyticsRowMatchesShow($show, $row, $liveId);
                });

                if (! is_array($raw)) {
                    throw new \RuntimeException('Whatnot returned analytics, but the rendered show identity did not verify this exact show after hydration retries. Skipped to prevent stale SPA metrics from being copied to another UUID.');
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

                if ($duplicate = $this->findSuspiciousDuplicateSignature($show, $fields)) {
                    throw new \RuntimeException(
                        "Analytics signature exactly matches show #{$duplicate->id} ({$duplicate->whatnot_show_id}); refusing to copy likely stale metrics."
                    );
                }

                $oldPayload = is_array($show->raw_import_payload) ? $show->raw_import_payload : [];
                $newPayload = $raw;
                if (array_key_exists('_analytics_quarantine', $oldPayload)) {
                    $newPayload['_analytics_quarantine_history'] = $oldPayload['_analytics_quarantine'];
                    $newPayload['_analytics_quarantine_repaired_at'] = now()->toIso8601String();
                }

                $fields['whatnot_show_id'] = $show->whatnot_show_id ?: $liveId;
                $fields['last_synced_at'] = now();
                $fields['raw_import_payload'] = $newPayload;
                $show->forceFill($fields);
                $show->setAttribute('last_analytics_synced_at', now());
                $show->save();
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

    private function analyticsRowMatchesShow(Show $show, array $row, string $liveId): bool
    {
        $payloadLiveId = $this->findLiveIdInPayload($row);
        if (! $payloadLiveId || $payloadLiveId !== strtolower($liveId)) {
            return false;
        }

        $expectedDate = $show->show_date?->format('Y-m-d');
        $actualDate = $this->normalizeDate($row['show_date'] ?? $row['date'] ?? null);
        $expectedTitle = $this->normalizeTitle((string) $show->title);
        $actualTitle = $this->normalizeTitle((string) ($row['title'] ?? $row['show_title'] ?? ''));

        $dateMatches = $expectedDate !== null && $actualDate !== null && $expectedDate === $actualDate;
        $titleMatches = $expectedTitle !== '' && $actualTitle !== '' && $expectedTitle === $actualTitle;

        // Multiple Whatnot shows can happen on the same date, so date alone is
        // not strong enough when both sides provide a title. Require the exact
        // normalized rendered title and reject ambiguous same-day stale cards.
        if ($expectedTitle !== '') {
            if ($actualTitle === '' || ! $titleMatches) {
                return false;
            }

            return $actualDate === null || $expectedDate === null || $dateMatches;
        }

        return $dateMatches;
    }

    private function findSuspiciousDuplicateSignature(Show $show, array $fields): ?Show
    {
        $signatureFields = [
            'gross_revenue', 'whatnot_net', 'completed_earnings',
            'avg_order_value', 'units_sold', 'buyers_count',
        ];

        $present = array_values(array_filter($signatureFields, fn (string $field) => array_key_exists($field, $fields)));
        if (count($present) < 4 || ! array_key_exists('gross_revenue', $fields)) {
            return null;
        }

        $query = Show::query()
            ->where('id', '!=', $show->id)
            ->whereNotNull('whatnot_show_id');

        if ($show->whatnot_show_id) {
            $query->where('whatnot_show_id', '!=', $show->whatnot_show_id);
        }

        foreach ($present as $field) {
            $query->where($field, $fields[$field]);
        }

        return $query->first();
    }

    private function resolveLiveId(Show $show, WhatnotScraper $scraper): ?string
    {
        foreach ([$show->whatnot_show_id, $show->detail_url] as $candidate) {
            if ($liveId = $this->extractLiveId((string) $candidate)) {
                return $liveId;
            }
        }

        if ($liveId = $this->findLiveIdInPayload($show->raw_import_payload)) {
            return $liveId;
        }

        return $this->discoverLiveId($show, $scraper);
    }

    private function discoverLiveId(Show $show, WhatnotScraper $scraper): ?string
    {
        if (! $this->sellerDiscoveryAttempted) {
            $this->sellerDiscoveryAttempted = true;
            $this->line('  Discovering historical Whatnot show URLs for unresolved records…');

            $channelUsername = trim((string) ($show->channel?->whatnot_username ?? ''));

            if ($channelUsername === '') {
                $this->discoveredSellerShows = [];
                $this->warn('  Historical show URL discovery skipped: show has no Whatnot channel username.');
            } else {
                try {
                    $this->discoveredSellerShows = array_values(array_filter(
                        $scraper->fetchSellerShowUrls(false, $channelUsername, 500),
                        fn ($row) => is_array($row) && ! empty($row['detail_url']) && $this->extractLiveId((string) $row['detail_url']) !== null,
                    ));
                    $this->line('  Discovery returned '.count($this->discoveredSellerShows).' show URL(s).');
                } catch (\Throwable $e) {
                    $this->discoveredSellerShows = [];
                    $this->warn('  Historical show URL discovery failed: '.$e->getMessage());
                    Log::warning('Missing Whatnot analytics: seller-show discovery failed', [
                        'channel' => $channelUsername,
                        'exception' => $e->getMessage(),
                    ]);
                }
            }
        }

        if (empty($this->discoveredSellerShows)) {
            return null;
        }

        $targetTitle = $this->normalizeTitle((string) $show->title);
        $targetDate = $show->show_date?->format('Y-m-d');

        $rows = collect($this->discoveredSellerShows)->filter(function (array $row) {
            return $this->extractLiveId((string) ($row['detail_url'] ?? '')) !== null;
        });

        $exact = $rows->filter(function (array $row) use ($targetTitle, $targetDate) {
            return $targetTitle !== ''
                && $targetDate !== null
                && $this->normalizeTitle((string) ($row['title'] ?? '')) === $targetTitle
                && $this->normalizeDate($row['show_date'] ?? null) === $targetDate;
        });

        $match = $exact->count() === 1 ? $exact->first() : null;

        if (! $match && $targetDate !== null) {
            $sameDate = $rows->filter(fn (array $row) => $this->normalizeDate($row['show_date'] ?? null) === $targetDate);
            if ($sameDate->count() === 1) {
                $match = $sameDate->first();
            }
        }

        if (! $match && $targetTitle !== '') {
            $sameTitle = $rows->filter(fn (array $row) => $this->normalizeTitle((string) ($row['title'] ?? '')) === $targetTitle);
            if ($sameTitle->count() === 1) {
                $match = $sameTitle->first();
            }
        }

        if (! is_array($match)) {
            return null;
        }

        $detailUrl = (string) ($match['detail_url'] ?? '');
        $liveId = $this->extractLiveId($detailUrl);
        if (! $liveId) {
            return null;
        }

        $show->forceFill([
            'whatnot_show_id' => $liveId,
            'detail_url' => $detailUrl,
        ])->save();

        $this->line("    resolved #{$show->id} → {$liveId}");

        return $liveId;
    }

    private function findLiveIdInPayload(mixed $value): ?string
    {
        if (is_string($value)) {
            return $this->extractLiveId($value);
        }

        if (! is_array($value)) {
            return null;
        }

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

    private function normalizeTitle(string $value): string
    {
        $value = html_entity_decode(trim($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = mb_strtolower($value);
        $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? $value;
        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }

    private function normalizeDate(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        try {
            return Carbon::parse((string) $value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function extractLiveId(string $value): ?string
    {
        return preg_match('/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i', $value, $m)
            ? strtolower($m[0])
            : null;
    }
}
