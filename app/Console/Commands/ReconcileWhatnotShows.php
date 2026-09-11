<?php

namespace App\Console\Commands;

use App\Models\Show;
use App\Models\WhatnotChannel;
use App\Services\WhatnotScraper;
use App\Support\WhatnotPipelineLock;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ReconcileWhatnotShows extends Command
{
    protected $signature = 'whatnot:reconcile-shows
        {--channel= : Channel name, username, or ID}
        {--days=90 : How far back to inspect past auto-imported shows}
        {--limit=100 : Maximum records to inspect per run}
        {--apply : Actually delete verified phantom/non-analytics shows}
        {--skip-if-busy : Skip cleanly if another Whatnot pipeline is active}';

    protected $description = 'Reconcile past auto-imported Whatnot shows against Seller Hub Current, Upcoming, and Past tabs.';

    public function handle(WhatnotScraper $scraper): int
    {
        $lock = WhatnotPipelineLock::acquire(
            'Whatnot show reconciliation',
            $this->option('skip-if-busy') ? 0 : 7200,
        );

        if (! $lock) {
            $message = WhatnotPipelineLock::busyMessage();
            if ($this->option('skip-if-busy')) {
                $this->line("Show reconciliation: skipped — {$message}");
                return self::SUCCESS;
            }
            $this->error($message);
            return self::FAILURE;
        }

        try {
            return $this->runReconciliation($scraper);
        } finally {
            WhatnotPipelineLock::release($lock);
        }
    }

    private function runReconciliation(WhatnotScraper $scraper): int
    {
        $days = max(1, min(3650, (int) $this->option('days')));
        $limit = max(1, min(500, (int) $this->option('limit')));
        $apply = (bool) $this->option('apply');
        $debug = filter_var((string) (getenv('WHATNOT_DEBUG') ?: '0'), FILTER_VALIDATE_BOOL);

        $query = Show::query()
            ->with('channel')
            ->where('import_source', 'auto_whatnot')
            ->whereDate('show_date', '>=', today()->subDays($days))
            ->where(function ($q) {
                $q->where(function ($timed) {
                    $timed->whereNotNull('start_time')
                        ->where('start_time', '<=', now());
                })->orWhere(function ($dateOnly) {
                    $dateOnly->whereNull('start_time')
                        ->whereDate('show_date', '<', today());
                });
            })
            ->whereNotIn('status', ['cancelled'])
            ->where(function ($q) {
                $q->whereNull('gross_revenue')->orWhere('gross_revenue', '<=', 0)
                    ->orWhereNull('whatnot_net')->orWhere('whatnot_net', '<=', 0);
            })
            ->orderByDesc('show_date')
            ->orderByDesc('start_time')
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
            $this->info('No past auto-imported shows with missing analytics need reconciliation.');
            return self::SUCCESS;
        }

        $this->info(sprintf(
            '%s %d past auto-imported show(s) against Seller Hub…',
            $apply ? 'Reconciling' : 'Previewing',
            $shows->count(),
        ));

        $verified = 0;
        $removed = 0;
        $protected = 0;
        $kept = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($shows as $show) {
            $liveId = $this->extractLiveId((string) ($show->whatnot_show_id ?: $show->detail_url));
            if (! $liveId && is_array($show->raw_import_payload)) {
                $liveId = $this->findLiveIdInPayload($show->raw_import_payload);
            }

            $this->line("  #{$show->id} {$show->show_date?->format('Y-m-d')} {$show->title}");

            if (! $liveId) {
                $skipped++;
                $this->warn('    skipped: no Whatnot UUID is available, so Seller Hub identity cannot be verified safely.');
                continue;
            }

            try {
                $rows = $scraper->fetchShows(
                    limit: 1,
                    debug: $debug,
                    channelUsername: $show->channel?->whatnot_username,
                    seedLiveId: $liveId,
                    expectedTitle: (string) $show->title,
                    expectedDate: $show->show_date?->format('Y-m-d'),
                );

                $candidate = collect($rows)->first(function ($row) use ($show, $liveId) {
                    if (! is_array($row) || empty($row['_seller_hub_verified'])) {
                        return false;
                    }

                    $isPhantom = ! empty($row['_seller_hub_absent_all_tabs']);
                    $hasNoAnalytics = ! empty($row['_analytics_unavailable']);

                    return ($isPhantom || $hasNoAnalytics)
                        && $this->markerMatchesShow($show, $row, $liveId);
                });

                if (is_array($candidate)) {
                    $verified++;
                    $reason = ! empty($candidate['_seller_hub_absent_all_tabs'])
                        ? 'UUID is absent from Seller Hub Past, Current, and Upcoming'
                        : 'exact Seller Hub Past row has no See Analytics action';

                    $dependencies = $this->protectedDependencies($show);
                    if ($dependencies !== []) {
                        $protected++;
                        $this->warn('    protected: '.$reason.'; kept because it has '.implode(', ', $dependencies).'.');
                        Log::warning('Whatnot reconciliation protected verified phantom show with downstream data', [
                            'show_id' => $show->id,
                            'live_id' => $liveId,
                            'reason' => $reason,
                            'dependencies' => $dependencies,
                        ]);
                        continue;
                    }

                    if (! $apply) {
                        $this->warn('    would remove: '.$reason.'.');
                        continue;
                    }

                    $showId = $show->id;
                    $showTitle = $show->title;
                    $show->delete();
                    $removed++;
                    $this->warn("    removed: #{$showId} {$showTitle} — {$reason}.");
                    Log::info('Whatnot reconciliation removed verified phantom show', [
                        'show_id' => $showId,
                        'live_id' => $liveId,
                        'reason' => $reason,
                    ]);
                    continue;
                }

                $analytics = collect($rows)->first(function ($row) use ($show, $liveId) {
                    return is_array($row)
                        && empty($row['_analytics_unavailable'])
                        && empty($row['_seller_hub_absent_all_tabs'])
                        && $this->markerMatchesShow($show, $row, $liveId);
                });

                if (is_array($analytics)) {
                    $kept++;
                    $this->info('    kept: exact Whatnot show exists and has a verified analytics destination.');
                    continue;
                }

                $skipped++;
                $this->warn('    skipped: Seller Hub could not prove this show is phantom; record left untouched.');
            } catch (\Throwable $e) {
                $failed++;
                $this->error('    failed: '.$e->getMessage());
                Log::warning('Whatnot show reconciliation failed', [
                    'show_id' => $show->id,
                    'live_id' => $liveId,
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        $this->newLine();
        $this->info(sprintf(
            'Reconciliation complete: %d verified cleanup candidate(s), %d removed, %d protected, %d kept, %d skipped, %d failed.%s',
            $verified,
            $removed,
            $protected,
            $kept,
            $skipped,
            $failed,
            $apply ? '' : ' Preview only — rerun with --apply to delete verified candidates.',
        ));

        return self::SUCCESS;
    }

    private function protectedDependencies(Show $show): array
    {
        $dependencies = [];

        if ($show->orders()->exists()) {
            $dependencies[] = 'orders';
        }
        if ($show->payouts()->exists()) {
            $dependencies[] = 'payouts';
        }
        if (method_exists($show, 'shipments') && $show->shipments()->exists()) {
            $dependencies[] = 'shipments';
        }
        if (method_exists($show, 'streamerLogEntry') && $show->streamerLogEntry()->exists()) {
            $dependencies[] = 'streamer report';
        }
        if (method_exists($show, 'deductionRequests') && $show->deductionRequests()->exists()) {
            $dependencies[] = 'inventory deduction';
        }

        return $dependencies;
    }

    private function markerMatchesShow(Show $show, array $row, string $liveId): bool
    {
        $payloadLiveId = $this->findLiveIdInPayload($row);
        if (! $payloadLiveId || strtolower($payloadLiveId) !== strtolower($liveId)) {
            return false;
        }

        $expectedTitle = $this->normalizeTitle((string) $show->title);
        $actualTitle = $this->normalizeTitle((string) ($row['title'] ?? $row['show_title'] ?? ''));
        if ($expectedTitle !== '' && $actualTitle !== '' && $expectedTitle !== $actualTitle) {
            return false;
        }

        $expectedDate = $show->show_date?->format('Y-m-d');
        $actualDate = $this->normalizeDate($row['show_date'] ?? $row['date'] ?? null);
        if ($expectedDate !== null && $actualDate !== null && $expectedDate !== $actualDate) {
            return false;
        }

        return true;
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

    private function extractLiveId(string $value): ?string
    {
        return preg_match('/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i', $value, $m)
            ? strtolower($m[0])
            : null;
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
            return \Carbon\Carbon::parse((string) $value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}
