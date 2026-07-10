<?php

namespace App\Services;

use App\Models\Show;
use App\Models\WhatnotChannel;
use App\Models\WhatnotLedgerEntry;
use App\Models\WhatnotShowOrder;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class WhatnotScraper
{
    private string $scriptPath;
    private string $nodeBin;

    public function __construct()
    {
        $this->scriptPath = base_path('scripts/whatnot-scraper.cjs');
        $this->nodeBin    = config('vortex.whatnot.node_bin', 'node');
    }

    /**
     * Test that credentials are valid and the account has seller access.
     * Returns ['connected' => true, 'email' => ..., 'seller_url' => ...].
     *
     * @throws \RuntimeException on failure
     */
    public function testConnection(bool $debug = false): array
    {
        $env = $this->baseEnv($debug);
        $env['WHATNOT_MODE'] = 'test';

        $process = $this->makeProcess($env, timeout: 60);

        $process->run();

        $stderr = trim($process->getErrorOutput());
        $stdout = trim($process->getOutput());

        if ($stderr) {
            Log::channel('stack')->warning('WhatnotScraper testConnection stderr', ['output' => $stderr]);
        }

        if (! $process->isSuccessful()) {
            $message = $stderr ?: "Scraper exited with code {$process->getExitCode()}";
            throw new \RuntimeException("Connection test failed: {$message}");
        }

        $data = json_decode($stdout, true);
        if (json_last_error() !== JSON_ERROR_NONE || empty($data['connected'])) {
            throw new \RuntimeException('Unexpected scraper response during test: ' . $stdout);
        }

        return $data;
    }

    /**
     * Run the analytics scraper and return raw show data for one channel.
     *
     * @throws \RuntimeException on login/nav failures
     */
    public function fetchShows(int $limit = 50, bool $debug = false, ?string $channelUsername = null): array
    {
        $env = $this->baseEnv($debug);
        $env['WHATNOT_MODE']  = 'analytics';
        $env['WHATNOT_LIMIT'] = (string) $limit;

        if ($channelUsername) {
            $env['WHATNOT_CHANNEL_NAME'] = $channelUsername;
        }

        // Analytics-nav walks the full channel history one show at a time, so a
        // channel with hundreds of past shows can legitimately run for many
        // minutes. 240s was too short and killed the walk mid-import; allow up to
        // 20 minutes per channel (scales roughly with --limit).
        $process = $this->makeProcess($env, timeout: 1200);
        $process->run();

        $stderr = trim($process->getErrorOutput());
        $stdout = trim($process->getOutput());

        if ($stderr) {
            Log::channel('stack')->warning('WhatnotScraper stderr', ['output' => $stderr, 'channel' => $channelUsername]);
            if ($debug) {
                // Echo stderr so `php artisan whatnot:import --debug` shows [whatnot] lines
                fwrite(STDERR, $stderr . "\n");
            }
        }

        if ($process->getExitCode() === 2) {
            // Pass all non-empty stderr lines through so diagnostics are visible in artisan/UI.
            $diagLines = array_filter(explode("\n", $stderr), fn ($l) => trim($l) !== '');
            $diag      = implode("\n", array_slice(array_values($diagLines), 0, 80));
            throw new \RuntimeException(
                "Whatnot scraper: page selectors didn't match.\n" . $diag
            );
        }

        if (! $process->isSuccessful()) {
            $message = $stderr ?: "Scraper exited with code {$process->getExitCode()}";
            throw new \RuntimeException("Whatnot scraper failed: {$message}");
        }

        if (empty($stdout)) {
            return [];
        }

        $data = json_decode($stdout, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Whatnot scraper returned invalid JSON: ' . json_last_error_msg());
        }

        return is_array($data) ? $data : [];
    }

    /**
     * Scrape /seller/shows to collect detail URLs for past shows.
     * Returns [{title, show_date, detail_url}].
     *
     * @throws \RuntimeException
     */
    public function fetchSellerShowUrls(bool $debug = false): array
    {
        $env = $this->baseEnv($debug);
        $env['WHATNOT_MODE'] = 'seller-shows';

        $process = $this->makeProcess($env, timeout: 120);

        $process->run();

        $stderr = trim($process->getErrorOutput());
        $stdout = trim($process->getOutput());

        if ($stderr) {
            Log::channel('stack')->warning('WhatnotScraper seller-shows stderr', ['output' => $stderr]);
        }

        if ($process->getExitCode() === 2) {
            throw new \RuntimeException(
                "Could not find show links on /seller/shows. " .
                "Run with WHATNOT_DEBUG=1 to capture screenshots and update the selector in scripts/whatnot-scraper.cjs."
            );
        }

        if (! $process->isSuccessful()) {
            throw new \RuntimeException("Seller-shows scraper failed: " . ($stderr ?: "exit code {$process->getExitCode()}"));
        }

        $data = json_decode($stdout, true);
        return is_array($data) ? $data : [];
    }

    /**
     * Scrape /seller/shows and backfill detail_url on existing Show records.
     * Matches by title + show_date. Returns ['updated' => n, 'unmatched' => n].
     */
    public function importDetailUrls(bool $debug = false): array
    {
        $rows    = $this->fetchSellerShowUrls($debug);
        $updated = 0;
        $unmatched = 0;

        foreach ($rows as $row) {
            if (empty($row['detail_url'])) {
                continue;
            }

            $title    = isset($row['title']) ? trim($row['title']) : null;
            $date     = $row['show_date'] ?? null;

            $query = Show::query()->whereNull('detail_url');

            if ($title && $date) {
                $query->where(function ($q) use ($title, $date) {
                    $q->where(fn ($q2) => $q2->where('title', $title)->whereDate('show_date', $date))
                      ->orWhere(fn ($q2) => $q2->whereDate('show_date', $date));
                });
            } elseif ($date) {
                $query->whereDate('show_date', $date);
            } elseif ($title) {
                $query->where('title', $title);
            } else {
                $unmatched++;
                continue;
            }

            $show = $query->orderByRaw($title ? "title = ? DESC" : "id DESC", $title ? [$title] : [])->first();

            if ($show) {
                $show->update(['detail_url' => $row['detail_url']]);
                $updated++;
                Log::info('WhatnotScraper: backfilled detail_url', ['show_id' => $show->id, 'url' => $row['detail_url']]);
            } else {
                $unmatched++;
            }
        }

        return compact('updated', 'unmatched');
    }

    /**
     * Scrape the order/lot list for a specific show detail URL.
     *
     * @throws \RuntimeException on scraper failures or selector misses
     */
    public function fetchShowOrders(string $showUrl, bool $debug = false): array
    {
        $env = $this->baseEnv($debug);
        $env['WHATNOT_MODE']     = 'show-orders';
        $env['WHATNOT_SHOW_URL'] = $showUrl;

        $process = $this->makeProcess($env);

        $process->run();

        $stderr = trim($process->getErrorOutput());
        $stdout = trim($process->getOutput());

        if ($stderr) {
            Log::channel('stack')->warning('WhatnotScraper show-orders stderr', ['output' => $stderr]);
        }

        if ($process->getExitCode() === 2) {
            throw new \RuntimeException(
                "Whatnot scraper: order row selectors didn't match the show page. " .
                "Set WHATNOT_DEBUG=1 and re-run, then update the order SELECTORS in scripts/whatnot-scraper.cjs."
            );
        }

        if (! $process->isSuccessful()) {
            $message = $stderr ?: "Scraper exited with code {$process->getExitCode()}";
            throw new \RuntimeException("Order scraper failed: {$message}");
        }

        if (empty($stdout)) {
            return [];
        }

        $data = json_decode($stdout, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Order scraper returned invalid JSON: ' . json_last_error_msg());
        }

        return is_array($data) ? $data : [];
    }

    /**
     * Scrape and persist orders for a given Show. Deduplicates on whatnot_order_id
     * (when available) or buyer+item+lot_number within the same show.
     *
     * @return array{created: int, skipped: int}
     */
    public function importShowOrders(Show $show, bool $debug = false): array
    {
        $liveId = $this->extractLiveIdFromUrl($show->detail_url);
        if (! $liveId) {
            throw new \RuntimeException("Show #{$show->id} has no livestream id — run `php artisan whatnot:import` first to capture the Whatnot show URL.");
        }

        // Route through the batched orders scrape (one show) so the per-show button
        // and the scheduled backfill use the same code path as the bulk import:
        // the /dashboard/orders?source=<id> table, paginated and deduped by order id.
        $show->loadMissing('channel');
        $ordersByShow = $this->fetchOrdersForShows(
            [['live_id' => $liveId, 'show_key' => $show->id]],
            $show->channel?->whatnot_username,
            $debug,
        );

        return $this->persistShowOrders($show, $ordersByShow[$show->id] ?? []);
    }

    /**
     * Persist already-scraped order rows onto a Show, deduplicating on
     * whatnot_order_id (when available) or buyer+item+lot_number within the show.
     *
     * @param  array<int,array<string,mixed>>  $rows
     * @return array{created: int, skipped: int}
     */
    public function persistShowOrders(Show $show, array $rows): array
    {
        $created = 0;
        $skipped = 0;

        // Pre-load all existing order IDs and fallback keys in two queries instead of one per row
        $existingOrderIds = WhatnotShowOrder::where('show_id', $show->id)
            ->whereNotNull('whatnot_order_id')
            ->pluck('whatnot_order_id')
            ->flip()
            ->toArray();

        $existingFallbackKeys = WhatnotShowOrder::where('show_id', $show->id)
            ->whereNull('whatnot_order_id')
            ->get(['buyer_username', 'item_name', 'lot_number'])
            ->map(fn ($r) => "{$r->buyer_username}|{$r->item_name}|{$r->lot_number}")
            ->flip()
            ->toArray();

        foreach ($rows as $row) {
            if (empty($row['buyer']) && empty($row['item_name'])) {
                $skipped++;
                continue;
            }

            $orderId = $row['order_id'] ?? null;

            if ($orderId) {
                $existing = isset($existingOrderIds[$orderId]);
            } else {
                $key      = ($row['buyer'] ?? '') . '|' . ($row['item_name'] ?? '') . '|' . ($row['lot_number'] ?? '');
                $existing = isset($existingFallbackKeys[$key]);
                // Guard against duplicate rows within this same batch
                $existingFallbackKeys[$key] = true;
            }

            if ($existing) {
                $skipped++;
                continue;
            }

            WhatnotShowOrder::create([
                'show_id'          => $show->id,
                'whatnot_order_id' => $orderId,
                'whatnot_show_url' => $show->detail_url,
                'buyer_username'   => $row['buyer'] ?? null,
                'lot_number'       => $row['lot_number'] ?? null,
                'item_name'        => $row['item_name'] ?? null,
                'quantity'         => $row['quantity'] ?? 1,
                'unit_price'       => $row['unit_price'] ?? null,
                'total_price'      => $row['total_price'] ?? null,
                'status'           => $row['status'] ?? 'completed',
                'show_date'        => $show->show_date,
                'raw_data'         => $row,
            ]);

            $created++;
        }

        Log::info('WhatnotScraper persistShowOrders complete', [
            'show_id' => $show->id,
            'created' => $created,
            'skipped' => $skipped,
        ]);

        return compact('created', 'skipped');
    }

    /**
     * Scrape orders for many shows in a single browser session (orders-batch mode).
     *
     * @param  array<int,array{live_id:string, show_key:int|string}>  $sources
     * @return array<int|string, array<int,array<string,mixed>>>  map of show_key => order rows
     */
    public function fetchOrdersForShows(array $sources, ?string $channelUsername = null, bool $debug = false): array
    {
        if (empty($sources)) {
            return [];
        }

        $srcFile = tempnam(sys_get_temp_dir(), 'wn-orders-') . '.json';
        file_put_contents($srcFile, json_encode(array_values($sources)));

        $env = $this->baseEnv($debug);
        $env['WHATNOT_MODE']                = 'orders-batch';
        $env['WHATNOT_ORDER_SOURCES_FILE']  = $srcFile;
        if ($channelUsername) {
            $env['WHATNOT_CHANNEL_NAME'] = $channelUsername;
        }

        // Each show is a separate page navigation in one session; scale the timeout
        // with the number of shows (login overhead + ~15s worst-case/show) and cap.
        $timeout = min(3600, 300 + count($sources) * 15);

        try {
            $process = $this->makeProcess($env, timeout: $timeout);
            $process->run();

            $stderr = trim($process->getErrorOutput());
            $stdout = trim($process->getOutput());

            if ($stderr) {
                Log::channel('stack')->warning('WhatnotScraper orders-batch stderr', ['output' => $stderr]);
                if ($debug) {
                    fwrite(STDERR, $stderr . "\n");
                }
            }

            if (! $process->isSuccessful() || empty($stdout)) {
                Log::warning('WhatnotScraper orders-batch produced no usable output', [
                    'exit' => $process->getExitCode(),
                ]);
                return [];
            }

            $data = json_decode($stdout, true);
            if (json_last_error() !== JSON_ERROR_NONE || ! is_array($data)) {
                Log::warning('WhatnotScraper orders-batch returned invalid JSON', ['error' => json_last_error_msg()]);
                return [];
            }

            $map = [];
            foreach ($data as $entry) {
                $key = $entry['show_key'] ?? null;
                if ($key === null) {
                    continue;
                }
                $map[$key] = $entry['orders'] ?? [];
            }

            return $map;
        } finally {
            @unlink($srcFile);
        }
    }

    /**
     * Scrape one Whatnot ledger date window (<=31 days) for a channel.
     *
     * @return array<int,array<string,mixed>>
     */
    public function fetchLedger(string $from, string $to, ?string $channelUsername = null, bool $debug = false): array
    {
        $env = $this->baseEnv($debug);
        $env['WHATNOT_MODE']        = 'ledger';
        $env['WHATNOT_LEDGER_FROM'] = $from;
        $env['WHATNOT_LEDGER_TO']   = $to;
        if ($channelUsername) {
            $env['WHATNOT_CHANNEL_NAME'] = $channelUsername;
        }

        $process = $this->makeProcess($env, timeout: 900);
        $process->run();

        $stderr = trim($process->getErrorOutput());
        $stdout = trim($process->getOutput());

        if ($stderr) {
            Log::channel('stack')->warning('WhatnotScraper ledger stderr', ['output' => $stderr]);
            if ($debug) {
                fwrite(STDERR, $stderr . "\n");
            }
        }

        if (! $process->isSuccessful() || empty($stdout)) {
            return [];
        }

        $data = json_decode($stdout, true);
        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($data)) {
            Log::warning('WhatnotScraper ledger returned invalid JSON', ['error' => json_last_error_msg()]);
            return [];
        }

        return $data;
    }

    /**
     * Import the Whatnot ledger for a channel across a date range, splitting into
     * <=31-day windows (Whatnot's max) and deduping on a stable per-row key.
     *
     * @return array{created: int, skipped: int, windows: int}
     */
    public function importLedger(?WhatnotChannel $channel, string $from, string $to, bool $debug = false): array
    {
        $cursor = Carbon::parse($from)->startOfDay();
        $end    = Carbon::parse($to)->startOfDay();

        $created = 0;
        $skipped = 0;
        $windows = 0;

        while ($cursor->lte($end)) {
            $wEnd = (clone $cursor)->addDays(30);
            if ($wEnd->gt($end)) {
                $wEnd = clone $end;
            }
            $windows++;

            $rows = $this->fetchLedger($cursor->toDateString(), $wEnd->toDateString(), $channel?->whatnot_username, $debug);
            foreach ($rows as $row) {
                if ($this->persistLedgerRow($channel, $row)) {
                    $created++;
                } else {
                    $skipped++;
                }
            }

            $cursor = (clone $wEnd)->addDay();
        }

        Log::info('WhatnotScraper importLedger complete', [
            'channel' => $channel?->name,
            'from'    => $from,
            'to'      => $to,
            'created' => $created,
            'skipped' => $skipped,
            'windows' => $windows,
        ]);

        return compact('created', 'skipped', 'windows');
    }

    /**
     * Persist one scraped ledger row, deduped by a hash of its identifying fields.
     * Returns true if a new row was created, false if it already existed.
     */
    private function persistLedgerRow(?WhatnotChannel $channel, array $row): bool
    {
        $amountRaw = $row['amount'] ?? null;
        $amount = ($amountRaw !== null && $amountRaw !== '')
            ? (float) str_replace(['$', ',', ' '], '', $amountRaw)   // "-$3.47" → -3.47
            : null;

        $dedup = md5(implode('|', [
            $channel?->id ?? '',
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
            'whatnot_channel_id' => $channel?->id,
            'created_date'       => $this->parseWnDateTime($row['created_date'] ?? null),
            'completed_date'     => $this->parseWnDateTime($row['completed_date'] ?? null),
            'amount'             => $amount,
            'listing_id'         => $row['listing_id'] ?? null,
            'whatnot_order_id'   => $row['order_id'] ?? null,
            'order_hash'         => $row['order_hash'] ?? null,
            'message'            => $row['message'] ?? null,
            'status'             => $row['status'] ?? null,
            'transaction_type'   => $row['transaction_type'] ?? null,
            'dedup_key'          => $dedup,
            'raw_data'           => $row,
        ]);

        return true;
    }

    private function parseWnDateTime(?string $s): ?string
    {
        if (! $s) {
            return null;
        }
        try {
            return Carbon::parse($s)->toDateTimeString();
        } catch (\Throwable) {
            return null;
        }
    }

    protected function makeProcess(array $env, int $timeout = 180): Process
    {
        // Pass PLAYWRIGHT_BROWSERS_PATH so Playwright's own API can locate the browser.
        // Always default to /opt/pw-browsers (the shared install location) when the var
        // isn't set in the web-server environment.
        if (! isset($env['PLAYWRIGHT_BROWSERS_PATH'])) {
            $pwPath = env('PLAYWRIGHT_BROWSERS_PATH');
            $env['PLAYWRIGHT_BROWSERS_PATH'] = $pwPath ?: '/opt/pw-browsers';
        }

        // Pass the Chromium path from the marker file as an env var so the Node process
        // skips the fs.existsSync check (which fails when the binary lives under /root/).
        // Precedence: explicit .env var > marker file written by artisan whatnot:setup-chromium
        if (! isset($env['PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH'])) {
            $explicit = env('PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH');
            if ($explicit) {
                $env['PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH'] = $explicit;
            } else {
                $marker = storage_path('chromium-path.txt');
                if (file_exists($marker)) {
                    $markerPath = trim(file_get_contents($marker));
                    // Only pass the path if it's actually accessible to the current process.
                    // When artisan runs as root the marker may point to /root/.cache/…,
                    // which www-data cannot read — letting Node fall through to its own scan.
                    if ($markerPath && file_exists($markerPath)) {
                        $env['PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH'] = $markerPath;
                    }
                }
            }
        }

        $process = new Process([$this->nodeBin, $this->scriptPath], null, $env);
        $process->setTimeout($timeout);
        return $process;
    }

    /**
     * Fetch shows and upsert them into the shows table for one channel.
     * Returns counts: ['created' => n, 'updated' => n, 'skipped' => n].
     */
    public function importShows(?WhatnotChannel $channel = null, int $limit = 50, bool $debug = false, bool $withOrders = true): array
    {
        $rows = $this->fetchShows($limit, $debug, $channel?->whatnot_username);

        $created = 0;
        $updated = 0;
        $skipped = 0;

        // Collect (Show, livestream id) pairs so we can batch-scrape their orders
        // in a single session after all shows are persisted.
        $orderTargets = [];

        foreach ($rows as $row) {
            if (empty($row['title']) && empty($row['show_date'])) {
                $skipped++;
                continue;
            }

            $lookupTitle = $row['title'] ? trim($row['title']) : null;
            $lookupDate  = $row['show_date'] ?? null;

            $query = Show::query()->where('import_source', 'auto_whatnot');

            if ($lookupTitle && $lookupDate) {
                $query->where('title', $lookupTitle)->whereDate('show_date', $lookupDate);
            } elseif ($lookupTitle) {
                $query->where('title', $lookupTitle);
            } elseif ($lookupDate) {
                $query->whereDate('show_date', $lookupDate);
            } else {
                $skipped++;
                continue;
            }

            $existing = $query->first();

            $payload = array_filter([
                'whatnot_channel_id'    => $channel?->id,
                'title'                 => $lookupTitle,
                'show_date'             => $lookupDate,
                'show_duration'         => $row['show_duration'] ?? null,
                'gross_revenue'         => $row['gross_revenue'] ?? null,
                'whatnot_net'           => $row['whatnot_net'] ?? null,
                'tips'                  => $row['tips'] ?? null,
                'units_sold'            => $row['units_sold'] ?? null,
                'detail_url'            => $row['detail_url'] ?? null,
                'completed_earnings'    => $row['completed_earnings'] ?? null,
                'avg_order_value'       => $row['avg_order_value'] ?? null,
                'giveaway_spend'        => $row['giveaway_spend'] ?? null,
                'giveaways_count'       => $row['giveaways_count'] ?? null,
                'buyers_count'          => $row['buyers_count'] ?? null,
                'first_time_buyers'     => $row['first_time_buyers'] ?? null,
                'returning_buyers'      => $row['returning_buyers'] ?? null,
                'shares_count'          => $row['shares_count'] ?? null,
                'max_concurrent_viewers' => $row['max_concurrent_viewers'] ?? null,
                'total_views'           => $row['total_views'] ?? null,
                'avg_order_rating'      => $row['avg_order_rating'] ?? null,
                'import_source'         => 'auto_whatnot',
            ], fn ($v) => $v !== null);

            if ($existing) {
                $updateFields = array_intersect_key($payload, array_flip([
                    'gross_revenue', 'whatnot_net', 'tips', 'units_sold', 'show_duration', 'detail_url',
                    'completed_earnings', 'avg_order_value', 'giveaway_spend', 'giveaways_count',
                    'buyers_count', 'first_time_buyers', 'returning_buyers', 'shares_count',
                    'max_concurrent_viewers', 'total_views', 'avg_order_rating',
                ]));

                if (! empty($updateFields)) {
                    $existing->update($updateFields);
                    $updated++;
                } else {
                    $skipped++;
                }
                $showModel = $existing;
            } else {
                // show_date is NOT NULL with no default — skip creation rather than crash
                if (! $lookupDate) {
                    $skipped++;
                    continue;
                }
                $show = Show::create(array_merge($payload, ['status' => 'draft', 'created_by' => auth()->id() ?? 1]));
                $show->detectStreamers();
                $created++;
                $showModel = $show;
            }

            // Record this show's livestream id for the batched order scrape.
            $liveId = $row['whatnot_live_id'] ?? $this->extractLiveIdFromUrl($row['detail_url'] ?? null);
            if ($withOrders && $liveId) {
                $orderTargets[] = ['show' => $showModel, 'live_id' => $liveId];
            }
        }

        $ordersCreated = 0;
        if ($withOrders && ! empty($orderTargets)) {
            $ordersCreated = $this->importOrdersForTargets($orderTargets, $channel?->whatnot_username, $debug);
        }

        Log::info('WhatnotScraper import complete', [
            'channel'        => $channel?->name,
            'created'        => $created,
            'updated'        => $updated,
            'skipped'        => $skipped,
            'orders_created' => $ordersCreated,
        ]);

        return compact('created', 'updated', 'skipped', 'ordersCreated');
    }

    /**
     * Extract a livestream UUID from a /dashboard/live/<uuid> or ?source=<uuid> URL.
     */
    private function extractLiveIdFromUrl(?string $url): ?string
    {
        if (! $url) {
            return null;
        }
        if (preg_match('/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i', $url, $m)) {
            return $m[0];
        }
        return null;
    }

    /**
     * Batch-scrape and persist orders for a set of (Show, live_id) targets.
     *
     * @param  array<int,array{show: Show, live_id: string}>  $targets
     * @return int  total orders created across all shows
     */
    private function importOrdersForTargets(array $targets, ?string $channelUsername, bool $debug): int
    {
        $sources = [];
        $byKey   = [];
        foreach ($targets as $t) {
            $key = $t['show']->id;
            $sources[] = ['live_id' => $t['live_id'], 'show_key' => $key];
            $byKey[$key] = $t['show'];
        }

        try {
            $ordersByShow = $this->fetchOrdersForShows($sources, $channelUsername, $debug);
        } catch (\Throwable $e) {
            // Order scraping is best-effort — never fail the whole show import over it.
            Log::error('WhatnotScraper: batched order scrape failed — ' . $e->getMessage());
            return 0;
        }

        $ordersCreated = 0;
        foreach ($ordersByShow as $showKey => $rows) {
            $show = $byKey[$showKey] ?? null;
            if (! $show || empty($rows)) {
                continue;
            }
            $res = $this->persistShowOrders($show, $rows);
            $ordersCreated += $res['created'];
        }

        return $ordersCreated;
    }

    /**
     * Import shows from all channels that have include_in_import = true.
     * Returns aggregated counts across all channels.
     */
    public function importAllEnabledChannels(int $limit = 50, bool $debug = false, bool $withOrders = true): array
    {
        $channels = WhatnotChannel::where('include_in_import', true)
            ->where('status', 'active')
            ->get();

        if ($channels->isEmpty()) {
            Log::warning('WhatnotScraper importAllEnabledChannels: no active channels with include_in_import=true');
            return ['created' => 0, 'updated' => 0, 'skipped' => 0, 'channels' => 0];
        }

        $totals = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];

        foreach ($channels as $channel) {
            Log::info("WhatnotScraper: importing channel \"{$channel->name}\" ({$channel->whatnot_username})");

            try {
                $result = $this->importShows($channel, $limit, $debug, $withOrders);
                $totals['created'] += $result['created'];
                $totals['updated'] += $result['updated'];
                $totals['skipped'] += $result['skipped'];
            } catch (\RuntimeException $e) {
                Log::error("WhatnotScraper: channel \"{$channel->name}\" failed — {$e->getMessage()}");
                $totals['errors'][] = "Channel \"{$channel->name}\": {$e->getMessage()}";
            }
        }

        $totals['channels'] = $channels->count();
        return $totals;
    }

    /**
     * Run discover mode: navigate to /seller/shows, intercept all JSON API calls,
     * and return structured info about each endpoint found.
     *
     * Use this once to identify Whatnot's internal REST paths, then build
     * targeted API calls against those endpoints instead of DOM scraping.
     *
     * @throws \RuntimeException on failure
     */
    public function runDiscover(?WhatnotChannel $channel = null, bool $debug = true, ?callable $onProgress = null): array
    {
        $env = $this->baseEnv($debug);
        $env['WHATNOT_MODE'] = 'discover';

        if ($channel?->whatnot_username) {
            $env['WHATNOT_CHANNEL_NAME'] = $channel->whatnot_username;
        }

        // Phase 1: ~20 nav pages × ~10 s + Phase 2: 5 shows × 5 tabs × ~8 s + Phase 3 = allow 10 minutes
        $process = $this->makeProcess($env, timeout: 600);

        if ($onProgress) {
            $process->start();
            while ($process->isRunning()) {
                if ($err = $process->getIncrementalErrorOutput()) {
                    foreach (explode("\n", trim($err)) as $line) {
                        if ($line !== '') $onProgress($line);
                    }
                }
                usleep(200_000);
            }
            // drain any remaining stderr after process exits
            if ($err = $process->getIncrementalErrorOutput()) {
                foreach (explode("\n", trim($err)) as $line) {
                    if ($line !== '') $onProgress($line);
                }
            }
        } else {
            $process->run();
        }

        $stderr = trim($process->getErrorOutput());
        $stdout = trim($process->getOutput());

        if ($stderr) {
            Log::channel('stack')->info('WhatnotScraper discover stderr', ['output' => $stderr]);
        }

        if (! $process->isSuccessful()) {
            throw new \RuntimeException("Discover failed: " . ($stderr ?: "exit {$process->getExitCode()}"));
        }

        // stdout is a small envelope: { output_file, summary }
        // The full JSON is written to a temp file to avoid pipe buffer limits
        $envelope = json_decode($stdout, true);
        if (isset($envelope['output_file']) && file_exists($envelope['output_file'])) {
            $data = json_decode(file_get_contents($envelope['output_file']), true);
            @unlink($envelope['output_file']);
        } else {
            // fallback: try parsing stdout directly (old behaviour / small results)
            $data = json_decode($stdout, true);
        }

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Discover returned invalid JSON: ' . substr($stdout, 0, 300));
        }

        return $data;
    }

    /**
     * Directly probe Whatnot's Phoenix Channels WebSocket (no DOM scraping).
     * Opens the WS, joins candidate seller_hub:* topics, collects 20 s of messages.
     * Use this to learn the real topic + event names that carry shows/orders/payouts.
     *
     * @throws \RuntimeException on failure
     */
    public function runWsExplore(?WhatnotChannel $channel = null, ?callable $onProgress = null): array
    {
        $env = $this->baseEnv(true);
        $env['WHATNOT_MODE'] = 'ws-explore';

        if ($channel?->whatnot_username) {
            $env['WHATNOT_CHANNEL_NAME'] = $channel->whatnot_username;
        }

        $process = $this->makeProcess($env, timeout: 120);

        if ($onProgress) {
            $process->start();
            while ($process->isRunning()) {
                if ($err = $process->getIncrementalErrorOutput()) {
                    foreach (explode("\n", trim($err)) as $line) {
                        if ($line !== '') $onProgress($line);
                    }
                }
                usleep(200_000);
            }
            if ($err = $process->getIncrementalErrorOutput()) {
                foreach (explode("\n", trim($err)) as $line) {
                    if ($line !== '') $onProgress($line);
                }
            }
        } else {
            $process->run();
        }

        $stdout = trim($process->getOutput());
        $stderr = trim($process->getErrorOutput());

        if ($stderr) {
            Log::channel('stack')->info('WhatnotScraper ws-explore stderr', ['output' => $stderr]);
        }

        if (! $process->isSuccessful()) {
            throw new \RuntimeException("ws-explore failed: " . ($stderr ?: "exit {$process->getExitCode()}"));
        }

        $envelope = json_decode($stdout, true);
        if (isset($envelope['output_file']) && file_exists($envelope['output_file'])) {
            $data = json_decode(file_get_contents($envelope['output_file']), true);
            @unlink($envelope['output_file']);
        } else {
            $data = json_decode($stdout, true);
        }

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('ws-explore returned invalid JSON: ' . substr($stdout, 0, 300));
        }

        return $data;
    }

    // ── Public helpers ────────────────────────────────────────────────────────

    /**
     * Path to the session cookie bootstrap file.
     */
    public function cookiesFilePath(): string
    {
        return storage_path('whatnot-cookies.json');
    }

    public function hasCookieFile(): bool
    {
        return file_exists($this->cookiesFilePath());
    }

    /**
     * Test whether saved session cookies still grant seller hub access.
     * Returns ['ok' => true, 'url' => ...] or throws RuntimeException.
     */
    public function testCookieAuth(): array
    {
        $process = $this->makeProcess([
            'WHATNOT_MODE'  => 'cookie-test',
            'WHATNOT_DEBUG' => '0',
        ], timeout: 30);

        $process->run();
        $stdout = trim($process->getOutput());
        $stderr = trim($process->getErrorOutput());

        if ($stderr) {
            Log::channel('stack')->info('WhatnotScraper cookie-test stderr', ['output' => $stderr]);
        }

        if (! $process->isSuccessful()) {
            throw new \RuntimeException('Cookie auth test failed: ' . ($stderr ?: "exit {$process->getExitCode()}"));
        }

        $data = json_decode($stdout, true);
        if (! $data || empty($data['ok'])) {
            throw new \RuntimeException('Cookie auth test returned unexpected response: ' . $stdout);
        }

        return $data;
    }

    /**
     * After a form-based login has primed the persistent profile,
     * dump those cookies to storage/whatnot-cookies.json for future use.
     */
    public function dumpSessionCookies(): int
    {
        [$email, $password] = $this->resolveCredentials();

        $process = $this->makeProcess([
            'WHATNOT_EMAIL'    => $email,
            'WHATNOT_PASSWORD' => $password,
            'WHATNOT_MODE'     => 'dump-cookies',
        ], timeout: 60);

        $process->run();

        if (! $process->isSuccessful()) {
            throw new \RuntimeException('Cookie dump failed: ' . ($process->getErrorOutput() ?: "exit {$process->getExitCode()}"));
        }

        $json = json_decode($process->getOutput(), true);
        if (! is_array($json)) {
            throw new \RuntimeException('Cookie dump returned invalid JSON');
        }

        file_put_contents($this->cookiesFilePath(), json_encode($json, JSON_PRETTY_PRINT));
        return count($json);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Build the base env array for the scraper process.
     * Credentials are included when available; cookie-only modes don't need them.
     */
    private function baseEnv(bool $debug = false): array
    {
        $env = ['WHATNOT_DEBUG' => $debug ? '1' : '0'];

        $email    = config('vortex.whatnot.email');
        $password = config('vortex.whatnot.password');
        if ($email)    $env['WHATNOT_EMAIL']    = $email;
        if ($password) $env['WHATNOT_PASSWORD'] = $password;

        return $env;
    }

    private function resolveCredentials(): array
    {
        $email    = config('vortex.whatnot.email');
        $password = config('vortex.whatnot.password');

        if (! $email || ! $password) {
            throw new \RuntimeException(
                'WHATNOT_EMAIL and WHATNOT_PASSWORD are not set. Add them to your .env file.'
            );
        }

        return [$email, $password];
    }
}
