<?php

namespace App\Console\Commands;

use App\Models\Show;
use App\Models\WhatnotChannel;
use App\Services\WhatnotScraper;
use App\Support\WhatnotPipelineLock;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class ReconcileWhatnotShows extends Command
{
    protected $signature = 'whatnot:reconcile-shows
        {--channel= : Channel name, username, or ID}
        {--days=90 : How far back to inspect past auto-imported shows}
        {--limit=100 : Maximum records to inspect per run}
        {--apply : Actually delete verified phantom/non-analytics shows}
        {--skip-if-busy : Skip cleanly if another Whatnot pipeline is active}';

    protected $description = 'Reconcile past auto-imported Whatnot shows against one channel-wide Seller Hub Current, Upcoming, and Past scan.';

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

        $nowTime = now()->format('H:i:s');

        $query = Show::query()
            ->with('channel')
            ->where('import_source', 'auto_whatnot')
            ->whereDate('show_date', '>=', today()->subDays($days))
            ->where(function ($q) use ($nowTime) {
                $q->whereDate('show_date', '<', today())
                    ->orWhere(function ($today) use ($nowTime) {
                        $today->whereDate('show_date', today())
                            ->where(function ($time) use ($nowTime) {
                                $time->whereNull('start_time')
                                    ->orWhereTime('start_time', '<=', $nowTime);
                            });
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
            '%s %d past auto-imported show(s) against Seller Hub using one scan per channel…',
            $apply ? 'Reconciling' : 'Previewing',
            $shows->count(),
        ));

        $verified = 0;
        $removed = 0;
        $protected = 0;
        $kept = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($shows->groupBy('whatnot_channel_id') as $channelShows) {
            /** @var Collection<int, Show> $channelShows */
            $channel = $channelShows->first()?->channel;
            $channelUsername = trim((string) ($channel?->whatnot_username ?? ''));

            if ($channelUsername === '') {
                foreach ($channelShows as $show) {
                    $this->line("  #{$show->id} {$show->show_date?->format('Y-m-d')} {$show->title}");
                    $skipped++;
                    $this->warn('    skipped: show has no Whatnot channel username, so Seller Hub cannot be verified safely.');
                }
                continue;
            }

            $this->newLine();
            $this->info("Building Seller Hub index once for @{$channelUsername}…");

            try {
                $index = $this->fetchSellerHubIndex($channelUsername, $debug);
            } catch (\Throwable $e) {
                $failed += $channelShows->count();
                $this->error("Seller Hub index failed for @{$channelUsername}: {$e->getMessage()}");
                Log::warning('Whatnot reconciliation channel index failed', [
                    'channel' => $channelUsername,
                    'show_ids' => $channelShows->pluck('id')->all(),
                    'exception' => $e->getMessage(),
                ]);
                continue;
            }

            $pastRows = collect($index['past'] ?? [])
                ->filter(fn ($row) => is_array($row) && $this->rowLiveId($row) !== null)
                ->keyBy(fn ($row) => strtolower((string) $this->rowLiveId($row)));

            $currentIds = array_fill_keys(array_map(
                fn ($id) => strtolower((string) $id),
                array_filter((array) ($index['current_ids'] ?? [])),
            ), true);
            $upcomingIds = array_fill_keys(array_map(
                fn ($id) => strtolower((string) $id),
                array_filter((array) ($index['upcoming_ids'] ?? [])),
            ), true);

            $canProveAbsence = ! empty($index['past_selected'])
                && ! empty($index['past_exhausted'])
                && ! empty($index['current_verified'])
                && ! empty($index['upcoming_verified']);

            $counts = (array) ($index['counts'] ?? []);
            $this->line(sprintf(
                '  Seller Hub index: %d Past, %d Current, %d Upcoming. Absence verification: %s',
                (int) ($counts['past'] ?? $pastRows->count()),
                (int) ($counts['current'] ?? count($currentIds)),
                (int) ($counts['upcoming'] ?? count($upcomingIds)),
                $canProveAbsence ? 'verified' : 'incomplete — absent UUIDs will be preserved',
            ));

            foreach ($channelShows as $show) {
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

                $liveId = strtolower($liveId);

                if (isset($currentIds[$liveId])) {
                    $kept++;
                    $this->info('    kept: UUID currently exists in Seller Hub Current.');
                    continue;
                }

                if (isset($upcomingIds[$liveId])) {
                    $kept++;
                    $this->info('    kept: UUID currently exists in Seller Hub Upcoming.');
                    continue;
                }

                $past = $pastRows->get($liveId);
                $reason = null;

                if (is_array($past)) {
                    $marker = $past;
                    $marker['whatnot_live_id'] = $liveId;
                    $marker['_seller_hub_verified'] = true;

                    if (! $this->markerMatchesShow($show, $marker, $liveId)) {
                        $skipped++;
                        $this->warn('    skipped: exact UUID was found in Past, but title/date identity did not match the database record.');
                        continue;
                    }

                    if (! empty($past['analytics_url'])) {
                        $kept++;
                        $this->info('    kept: exact Seller Hub Past row exists and has a See Analytics action.');
                        continue;
                    }

                    $reason = 'exact Seller Hub Past row has no See Analytics action';
                } else {
                    if (! $canProveAbsence) {
                        $skipped++;
                        $this->warn('    skipped: Seller Hub index did not fully exhaust every tab, so absence is not safe deletion evidence.');
                        continue;
                    }

                    $reason = 'UUID is absent from Seller Hub Past, Current, and Upcoming';
                }

                $verified++;
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

    private function fetchSellerHubIndex(string $channelUsername, bool $debug): array
    {
        $env = [
            'WHATNOT_MODE' => 'reconcile-index',
            'WHATNOT_CHANNEL_NAME' => ltrim($channelUsername, '@'),
            'WHATNOT_DEBUG' => $debug ? '1' : '0',
            'WHATNOT_RECONCILE_MAX_PASSES' => '100',
            'WHATNOT_EMAIL' => config('vortex.whatnot.email'),
            'WHATNOT_PASSWORD' => config('vortex.whatnot.password'),
            'WHATNOT_PYTHON_BIN' => config('vortex.whatnot.python_bin', 'python3'),
            'WHATNOT_BROWSER_BACKEND' => config('vortex.whatnot.browser_backend', 'local'),
            'WHATNOT_BROWSER_LOCK_WAIT' => (string) config('vortex.whatnot.browser_lock_wait', 1200),
            'WHATNOT_SCRAPLING_USE_CDP' => config('vortex.whatnot.scrapling_use_cdp', false) ? '1' : '0',
            'WHATNOT_SCRAPLING_BLOCK_WEBRTC' => config('vortex.whatnot.scrapling_block_webrtc', false) ? 'true' : 'false',
            'WHATNOT_SCRAPLING_HIDE_CANVAS' => config('vortex.whatnot.scrapling_hide_canvas', false) ? 'true' : 'false',
            'WHATNOT_SCRAPLING_ALLOW_WEBGL' => config('vortex.whatnot.scrapling_allow_webgl', true) ? 'true' : 'false',
            'WHATNOT_SCRAPER_FALLBACK' => config('vortex.whatnot.scraper_fallback', false) ? '1' : '0',
            'WHATNOT_HEADLESS' => config('vortex.whatnot.headless', false) ? 'true' : 'false',
            'WHATNOT_COOKIES_FILE' => config('vortex.whatnot.cookies_file'),
            'PLAYWRIGHT_BROWSERS_PATH' => config('vortex.whatnot.playwright_browsers_path'),
            'PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH' => config('vortex.whatnot.playwright_chromium_executable'),
            'WHATNOT_PROXY' => config('vortex.whatnot.proxy'),
        ];

        $env = array_filter($env, static fn ($value) => $value !== null && $value !== '');

        $process = new Process(
            [config('vortex.whatnot.node_bin', 'node'), base_path('scripts/whatnot-runner.cjs')],
            base_path(),
            $env,
        );
        $process->setTimeout(3600);

        $process->run(function (string $type, string $buffer) use ($debug): void {
            if ($debug && $type === Process::ERR) {
                fwrite(STDERR, $buffer);
            }
        });

        $stderr = trim($process->getErrorOutput());
        if (! $process->isSuccessful()) {
            throw new \RuntimeException($stderr ?: "Seller Hub index process exited with code {$process->getExitCode()}");
        }

        $stdout = trim($process->getOutput());
        if ($stdout === '') {
            throw new \RuntimeException('Seller Hub index returned no JSON output.');
        }

        $data = json_decode($stdout, true);
        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($data) || empty($data['_seller_hub_index'])) {
            throw new \RuntimeException('Seller Hub index returned invalid JSON: '.json_last_error_msg());
        }

        return $data;
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

    private function rowLiveId(array $row): ?string
    {
        foreach (['live_id', 'whatnot_live_id', 'whatnot_show_id', 'show_id', 'open_url', 'detail_url', 'url', 'href'] as $key) {
            if (array_key_exists($key, $row) && ($liveId = $this->findLiveIdInPayload($row[$key]))) {
                return $liveId;
            }
        }

        return null;
    }

    private function findLiveIdInPayload(mixed $value): ?string
    {
        if (is_string($value)) {
            return $this->extractLiveId($value);
        }
        if (! is_array($value)) {
            return null;
        }

        foreach (['whatnot_live_id', 'live_id', 'whatnot_show_id', 'show_id', 'detail_url', 'open_url', 'url', 'href'] as $key) {
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
