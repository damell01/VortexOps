<?php

namespace App\Services;

use App\Models\Shipment;
use App\Models\Show;
use App\Models\ShowIngestionLog;
use App\Models\StreamerLogEntry;
use App\Models\WhatnotChannel;
use App\Models\WhatnotLedgerEntry;
use App\Models\WhatnotShowOrder;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class WhatnotScraper
{
    /**
     * What the scraper's exit code means, and therefore what to do about it.
     * Mirrors the EXIT map in scripts/whatnot-scraper.cjs.
     */
    public const EXIT_SELECTOR_MISS  = 2;
    public const EXIT_AUTH_REQUIRED  = 3;
    public const EXIT_RATE_LIMITED   = 4;

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

        $this->withBrowserLock(fn () => $process->run());

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
    public function fetchShows(int $limit = 50, bool $debug = false, ?string $channelUsername = null, ?callable $onProgress = null): array
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
        // 20 minutes per channel at the ~50-show default. That ceiling was never
        // actually scaled with $limit despite the comment claiming it did — a
        // --type=full backfill (limit 500) hit the same 1200s wall and got killed
        // mid-walk. Scale it so a full historical backfill gets proportionally
        // more time instead of failing on any channel with deep history.
        $timeoutSeconds = max(1200, (int) ceil($limit / 50) * 1200);
        $process = $this->makeProcess($env, timeout: $timeoutSeconds);

        // Wait-for-lock needs to scale the same way the process timeout above
        // does — a --type=full run queued behind another long-running full
        // sync could legitimately need to wait more than the 1200s default,
        // and failing that wait isn't meaningfully different from the run
        // itself timing out.
        $this->withBrowserLock(function () use ($process, $onProgress) {
            if ($onProgress) {
                $this->streamProcess($process, $onProgress);
            } else {
                $process->run();
            }
        }, waitSeconds: $timeoutSeconds);

        $stderr = trim($process->getErrorOutput());
        $stdout = trim($process->getOutput());

        if ($stderr) {
            Log::channel('stack')->warning('WhatnotScraper stderr', ['output' => $stderr, 'channel' => $channelUsername]);
            if ($debug) {
                // Echo stderr so `php artisan whatnot:import --debug` shows [whatnot] lines
                fwrite(STDERR, $stderr . "\n");
            }
        }

        $this->throwForExitCode((int) $process->getExitCode(), $stderr);

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

        $this->withBrowserLock(fn () => $process->run());

        $stderr = trim($process->getErrorOutput());
        $stdout = trim($process->getOutput());

        if ($stderr) {
            Log::channel('stack')->warning('WhatnotScraper seller-shows stderr', ['output' => $stderr]);
        }

        $this->throwForExitCode((int) $process->getExitCode(), $stderr);

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

        $this->withBrowserLock(fn () => $process->run());

        $stderr = trim($process->getErrorOutput());
        $stdout = trim($process->getOutput());

        if ($stderr) {
            Log::channel('stack')->warning('WhatnotScraper show-orders stderr', ['output' => $stderr]);
        }

        $this->throwForExitCode((int) $process->getExitCode(), $stderr);

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
    public function importShowOrders(Show $show, bool $debug = false, ?callable $onProgress = null): array
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
            $onProgress,
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
        $updated = 0;

        // Default each new order's location to the show's streamer's own inventory
        // location, so the streamer's Items editor is pre-filled with their location.
        $defaultLocationId = $show->defaultInventoryLocation()?->id;

        // Pre-load existing orders keyed by whatnot_order_id — needed as full models
        // (not just an existence set) so a shipments-batch row carrying weight/dims/
        // carrier can be merged onto the matching record instead of just skipped.
        $existingByOrderId = WhatnotShowOrder::where('show_id', $show->id)
            ->whereNotNull('whatnot_order_id')
            ->get()
            ->keyBy('whatnot_order_id');

        $existingFallbackKeys = WhatnotShowOrder::where('show_id', $show->id)
            ->whereNull('whatnot_order_id')
            ->get(['buyer_username', 'item_name', 'lot_number'])
            ->map(fn ($r) => "{$r->buyer_username}|{$r->item_name}|{$r->lot_number}")
            ->flip()
            ->toArray();

        foreach ($rows as $row) {
            $orderId = $row['order_id'] ?? null;

            if ($orderId && $existingByOrderId->has($orderId)) {
                if ($this->mergeShipmentFields($existingByOrderId->get($orderId), $row)) {
                    $updated++;
                } else {
                    $skipped++;
                }
                continue;
            }

            if (empty($row['buyer']) && empty($row['item_name'])) {
                $skipped++;
                continue;
            }

            if (! $orderId) {
                $key = ($row['buyer'] ?? '') . '|' . ($row['item_name'] ?? '') . '|' . ($row['lot_number'] ?? '');
                if (isset($existingFallbackKeys[$key])) {
                    $skipped++;
                    continue;
                }
                // Guard against duplicate rows within this same batch
                $existingFallbackKeys[$key] = true;
            }

            WhatnotShowOrder::create([
                'show_id'               => $show->id,
                'whatnot_order_id'      => $orderId,
                'whatnot_show_url'      => $show->detail_url,
                'inventory_location_id' => $defaultLocationId,
                'buyer_username'        => $row['buyer'] ?? null,
                'lot_number'            => $row['lot_number'] ?? null,
                'item_name'             => $row['item_name'] ?? null,
                'quantity'              => $row['quantity'] ?? 1,
                'unit_price'            => $row['unit_price'] ?? null,
                'total_price'           => $row['total_price'] ?? null,
                'status'                => $row['status'] ?? 'completed',
                'show_date'             => $show->show_date,
                'raw_data'              => $row,
            ]);

            $created++;
        }

        Log::info('WhatnotScraper persistShowOrders complete', [
            'show_id' => $show->id,
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
        ]);

        return compact('created', 'skipped', 'updated');
    }

    /**
     * Persist individual shipment records for a show from scraped shipment data.
     * Creates new Shipment records and updates existing ones by tracking number.
     *
     * @return array{created: int, updated: int, skipped: int}
     */
    public function persistShipments(Show $show, array $rows): array
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;

        $existingByTracking = Shipment::where('show_id', $show->id)
            ->whereNotNull('tracking_number')
            ->get()
            ->keyBy('tracking_number');

        foreach ($rows as $row) {
            $trackingNumber = $row['tracking_number'] ?? null;

            if (! $trackingNumber) {
                $skipped++;
                continue;
            }

            if ($existingByTracking->has($trackingNumber)) {
                $shipment = $existingByTracking->get($trackingNumber);
                $updateData = array_filter([
                    'buyer_username'     => $row['buyer'] ?? null,
                    'item_count'         => $row['quantity'] ?? null,
                    'shipping_cost'      => $row['total_price'] ?? null,
                    'weight_oz'          => $row['weight_oz'] ?? null,
                    'dimensions_json'    => $this->parseDimensions($row),
                    'status'             => $row['shipping_status_scraped'] ?? null,
                    'carrier'            => $row['shipping_carrier'] ?? null,
                    'insurance_added'    => $this->detectInsuranceAdded($row),
                    'signature_required' => $this->detectSignatureRequired($row),
                    'raw_payload'        => $row,
                ], fn ($v) => $v !== null && $v !== '');

                if (! empty($updateData)) {
                    $shipment->update($updateData);
                    $updated++;
                }
                continue;
            }

            Shipment::create([
                'show_id'            => $show->id,
                'whatnot_order_id'   => $row['order_id'] ?? null,
                'buyer_username'     => $row['buyer'] ?? null,
                'created_at_whatnot' => $show->show_date,
                'item_count'         => $row['quantity'] ?? null,
                'shipping_cost'      => $row['total_price'] ?? null,
                'weight_oz'          => $row['weight_oz'] ?? null,
                'dimensions_json'    => $this->parseDimensions($row),
                'status'             => $row['shipping_status_scraped'] ?? null,
                'carrier'            => $row['shipping_carrier'] ?? null,
                'tracking_number'    => $trackingNumber,
                'insurance_added'    => $this->detectInsuranceAdded($row),
                'signature_required' => $this->detectSignatureRequired($row),
                'raw_payload'        => $row,
            ]);

            $created++;
        }

        Log::info('WhatnotScraper persistShipments complete', [
            'show_id' => $show->id,
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
        ]);

        return compact('created', 'updated', 'skipped');
    }

    /**
     * Parse box dimensions from raw row data into a structured format.
     */
    private function parseDimensions(array $row): ?array
    {
        $dims = array_filter([
            'length_in' => $row['box_length_in'] ?? null,
            'width_in'  => $row['box_width_in'] ?? null,
            'height_in' => $row['box_height_in'] ?? null,
        ], fn ($v) => $v !== null);

        return ! empty($dims) ? $dims : null;
    }

    /**
     * Detect if insurance was added from raw row data.
     */
    private function detectInsuranceAdded(array $row): bool
    {
        $text = ($row['raw_text'] ?? '') . ' ' . implode(' ', $row);
        return (bool) preg_match('/insurance\s+added/i', $text);
    }

    /**
     * Detect if signature was required from raw row data.
     */
    private function detectSignatureRequired(array $row): bool
    {
        $text = ($row['raw_text'] ?? '') . ' ' . implode(' ', $row);
        return (bool) preg_match('/signature\s+required/i', $text);
    }

    /**
     * Merge shipment metadata (weight/dims/carrier/shipping status) from a
     * scraped row onto an already-persisted order. Only non-null incoming
     * fields are applied, and shipping_status never regresses to an earlier
     * stage — guards against a stale/cached shipments-page scrape overwriting
     * a status ops already advanced manually. Returns true if anything changed.
     */
    private function mergeShipmentFields(WhatnotShowOrder $order, array $row): bool
    {
        $fields = array_filter([
            'shipment_weight_oz' => $row['weight_oz'] ?? null,
            'box_length_in'      => $row['box_length_in'] ?? null,
            'box_width_in'       => $row['box_width_in'] ?? null,
            'box_height_in'      => $row['box_height_in'] ?? null,
            'shipping_carrier'   => $row['shipping_carrier'] ?? null,
            'shipping_service'   => $row['shipping_service'] ?? null,
            'shipping_status'    => $row['shipping_status_scraped'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        if (empty($fields)) {
            return false;
        }

        if (isset($fields['shipping_status'])) {
            $rank         = array_flip(array_keys(WhatnotShowOrder::shippingStatusLabels()));
            $incomingRank = $rank[$fields['shipping_status']] ?? 0;
            $currentRank  = $order->shipping_status ? ($rank[$order->shipping_status] ?? 0) : -1;

            if ($incomingRank < $currentRank) {
                unset($fields['shipping_status']);
            }
        }

        if (empty($fields)) {
            return false;
        }

        $fields['shipment_synced_at'] = now();
        $order->update($fields);

        return true;
    }

    /**
     * Refresh shipment metadata (weight/dims/carrier/status) for a batch of shows
     * that already have orders imported. Shows with no resolvable livestream id
     * are skipped (counted, not errored) — resolves against the same
     * "/dashboard/live/<uuid>" / "?source=<uuid>" pattern used elsewhere.
     *
     * @param  \Illuminate\Support\Collection<int,Show>  $shows
     * @return array{updated: int, skipped_shows: int}
     */
    public function refreshShipmentsForShows(\Illuminate\Support\Collection $shows, ?string $channelUsername = null, bool $debug = false): array
    {
        $sources = [];
        $byKey   = [];
        foreach ($shows as $show) {
            $liveId = $this->extractLiveIdFromUrl($show->detail_url);
            if (! $liveId) {
                continue;
            }
            $sources[] = ['live_id' => $liveId, 'show_key' => $show->id];
            $byKey[$show->id] = $show;
        }

        $skippedShows = $shows->count() - count($sources);

        if (empty($sources)) {
            return ['updated' => 0, 'skipped_shows' => $skippedShows];
        }

        try {
            $shipmentsByShow = $this->fetchShipmentsForShows($sources, $channelUsername, $debug);
        } catch (\Throwable $e) {
            // Shipment refresh is best-effort — never blow up the caller over it.
            Log::error('WhatnotScraper: shipments refresh failed — ' . $e->getMessage());
            return ['updated' => 0, 'skipped_shows' => $shows->count()];
        }

        $updated = 0;
        foreach ($shipmentsByShow as $showKey => $rows) {
            $show = $byKey[$showKey] ?? null;
            if (! $show || empty($rows)) {
                continue;
            }
            // Update orders with shipment metadata
            $orderRes = $this->persistShowOrders($show, $rows);
            $updated += $orderRes['updated'] ?? 0;

            // Also create individual Shipment records
            $shipmentRes = $this->persistShipments($show, $rows);
            $updated += $shipmentRes['created'] ?? 0;
        }

        return ['updated' => $updated, 'skipped_shows' => $skippedShows];
    }

    /**
     * Scrape orders for many shows in a single browser session (orders-batch mode).
     *
     * @param  array<int,array{live_id:string, show_key:int|string}>  $sources
     * @return array<int|string, array<int,array<string,mixed>>>  map of show_key => order rows
     */
    public function fetchOrdersForShows(array $sources, ?string $channelUsername = null, bool $debug = false, ?callable $onProgress = null): array
    {
        return $this->runBatchScrape('orders-batch', $sources, $channelUsername, $debug, $onProgress);
    }

    /**
     * Refresh weight/dimensions/carrier/shipping-status for shows that already
     * have orders imported, scraping /dashboard/shipments?source=<id> directly.
     * Returned rows are merge-only — persistShowOrders() updates existing orders
     * matched by whatnot_order_id and never creates new ones from this source.
     *
     * @param  array<int,array{live_id:string, show_key:int|string}>  $sources
     * @return array<int|string, array<int,array<string,mixed>>>  map of show_key => shipment rows
     */
    public function fetchShipmentsForShows(array $sources, ?string $channelUsername = null, bool $debug = false, ?callable $onProgress = null): array
    {
        return $this->runBatchScrape('shipments-batch', $sources, $channelUsername, $debug, $onProgress);
    }

    /**
     * Discover shows from /dashboard/lives and scrape shipments for each.
     * Does not require pre-extracted livestream IDs — discovers them on demand
     * by visiting the live shows page. Returns shipment rows keyed by livestream_id.
     *
     * @return array<string, array<int,array<string,mixed>>>  map of live_id => shipment rows
     */
    public function fetchShipmentsFromLivePage(?string $channelUsername = null, bool $debug = false, ?callable $onProgress = null): array
    {
        return $this->runSimpleScrape('shipments-live', $channelUsername, $debug, $onProgress);
    }

    /**
     * Shared driver for the orders-batch and shipments-batch scraper modes —
     * both take the same [{live_id, show_key}] input and return the same
     * {show_key => rows} shape, differing only in which Whatnot page they scrape.
     *
     * @param  array<int,array{live_id:string, show_key:int|string}>  $sources
     * @return array<int|string, array<int,array<string,mixed>>>
     */
    private function runBatchScrape(string $mode, array $sources, ?string $channelUsername, bool $debug, ?callable $onProgress = null): array
    {
        if (empty($sources)) {
            return [];
        }

        $srcFile = tempnam(sys_get_temp_dir(), 'wn-' . $mode . '-') . '.json';
        file_put_contents($srcFile, json_encode(array_values($sources)));

        $env = $this->baseEnv($debug);
        $env['WHATNOT_MODE']               = $mode;
        $env['WHATNOT_ORDER_SOURCES_FILE'] = $srcFile;
        if ($channelUsername) {
            $env['WHATNOT_CHANNEL_NAME'] = $channelUsername;
        }

        // Each show is a separate page navigation in one session; scale the timeout
        // with the number of shows (login overhead + ~15s worst-case/show) and cap.
        $timeout = min(3600, 300 + count($sources) * 15);

        try {
            $process = $this->makeProcess($env, timeout: $timeout);
            $this->withBrowserLock(function () use ($process, $onProgress) {
                if ($onProgress) {
                    $this->streamProcess($process, $onProgress);
                } else {
                    $process->run();
                }
            });

            $stderr = trim($process->getErrorOutput());
            $stdout = trim($process->getOutput());

            if ($stderr) {
                Log::channel('stack')->warning("WhatnotScraper {$mode} stderr", ['output' => $stderr]);
                if ($debug) {
                    fwrite(STDERR, $stderr . "\n");
                }
            }

            if (! $process->isSuccessful() || empty($stdout)) {
                Log::warning("WhatnotScraper {$mode} produced no usable output", [
                    'exit' => $process->getExitCode(),
                ]);
                return [];
            }

            $data = json_decode($stdout, true);
            if (json_last_error() !== JSON_ERROR_NONE || ! is_array($data)) {
                Log::warning("WhatnotScraper {$mode} returned invalid JSON", ['error' => json_last_error_msg()]);
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
     * Run a scraper mode that doesn't require pre-extracted sources (e.g., shipments-live).
     * The scraper discovers its own data on the page.
     *
     * @return array<string, array<int,array<string,mixed>>>  map of live_id => rows
     */
    private function runSimpleScrape(string $mode, ?string $channelUsername, bool $debug, ?callable $onProgress = null): array
    {
        $env = $this->baseEnv($debug);
        $env['WHATNOT_MODE'] = $mode;
        if ($channelUsername) {
            $env['WHATNOT_CHANNEL_NAME'] = $channelUsername;
        }

        $timeout = 3600; // 1 hour max for discovery modes

        try {
            $process = $this->makeProcess($env, timeout: $timeout);
            $this->withBrowserLock(function () use ($process, $onProgress) {
                if ($onProgress) {
                    $this->streamProcess($process, $onProgress);
                } else {
                    $process->run();
                }
            });

            $stderr = trim($process->getErrorOutput());
            $stdout = trim($process->getOutput());

            if ($stderr) {
                Log::channel('stack')->warning("WhatnotScraper {$mode} stderr", ['output' => $stderr]);
                if ($debug) {
                    fwrite(STDERR, $stderr . "\n");
                }
            }

            if (!$process->isSuccessful() || empty($stdout)) {
                Log::warning("WhatnotScraper {$mode} produced no usable output", [
                    'exit' => $process->getExitCode(),
                ]);
                return [];
            }

            $data = json_decode($stdout, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
                Log::warning("WhatnotScraper {$mode} returned invalid JSON", ['error' => json_last_error_msg()]);
                return [];
            }

            $map = [];
            foreach ($data as $entry) {
                $key = $entry['live_id'] ?? null;
                if ($key === null) {
                    continue;
                }
                $map[$key] = $entry['orders'] ?? [];
            }

            return $map;
        } finally {
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
        $this->withBrowserLock(fn () => $process->run());

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
            $pwPath = config('vortex.whatnot.playwright_browsers_path');
            $env['PLAYWRIGHT_BROWSERS_PATH'] = $pwPath ?: '/opt/pw-browsers';
        }

        // Pass the Chromium path from the marker file as an env var so the Node process
        // skips the fs.existsSync check (which fails when the binary lives under /root/).
        // Precedence: explicit .env var > marker file written by artisan whatnot:setup-chromium
        if (! isset($env['PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH'])) {
            $explicit = config('vortex.whatnot.playwright_chromium_executable');
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
     * Run a process non-blocking, polling stderr and forwarding each line to
     * $onProgress as it arrives — the live-output counterpart to $process->run().
     * Still leaves getOutput()/getErrorOutput() fully populated afterward since
     * Symfony Process buffers regardless of polling.
     */
    private function streamProcess(Process $process, callable $onProgress): void
    {
        $process->start();
        while ($process->isRunning()) {
            // isRunning() alone never enforces the timeout set via setTimeout() —
            // only wait()/run() do that internally. This manual polling loop bypasses
            // both, so without this call a wedged child process (network stall, a
            // page that never reaches networkidle) runs forever instead of throwing
            // ProcessTimedOutException like every non-streaming scraper call does.
            $process->checkTimeout();

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
    }

    /**
     * Serialize every Chromium-launching task behind one shared lock.
     *
     * All whatnot:* work (shows, orders, ledger, discover, cookie dumps) drives
     * the SAME persistent Chromium profile. Two runs at once corrupt that
     * profile and produce garbage/empty scrapes, so import/orders/ledger — run
     * from cron, Filament, or the CLI — must take turns. The callback holds the
     * lock only while the browser process runs; DB persistence happens after it
     * is released. TTL must safely exceed the longest process timeout any
     * caller can pass to makeProcess() so a crashed run can't wedge the lock
     * forever — but if the TTL is SHORTER than a legitimate still-running
     * process, the lock auto-expires mid-operation and lets a second process
     * start a competing Chromium instance against the same profile, which is
     * its own (worse) way to corrupt things. Longest known caller today is
     * fetchShows() with type=full (500 shows): up to 12000s. Keep this
     * comfortably above that.
     *
     * @template T
     * @param  callable():T  $fn
     * @return T
     */
    protected function withBrowserLock(callable $fn, int $waitSeconds = 1200)
    {
        $lock = Cache::lock('whatnot:browser', 13800);

        try {
            // block() waits up to $waitSeconds for the lock, throwing on timeout.
            $lock->block($waitSeconds);
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException $e) {
            throw new \RuntimeException(
                'Timed out waiting for the shared Whatnot browser lock — another import/orders/ledger task is still ' .
                'running, OR a previous run was killed (pkill -9, Ctrl+C, OOM) before it could release the lock ' .
                'cleanly, leaving it stuck for up to its TTL. Check `ps aux | grep whatnot-scraper` first — if ' .
                'nothing real is running, clear it with `php artisan whatnot:unlock`.',
                0,
                $e
            );
        }

        // A process killed with SIGKILL never reaches the finally block below,
        // so the lock would otherwise sit "held" for its full TTL even though
        // nothing is actually running. Track the holder's PID so whatnot:unlock
        // can tell a genuinely stuck lock apart from a still-running one.
        Cache::put('whatnot:browser:holder_pid', getmypid(), 13800);

        try {
            return $fn();
        } finally {
            Cache::forget('whatnot:browser:holder_pid');
            $lock->release();
        }
    }

    /**
     * Fetch shows and upsert them into the shows table for one channel.
     * Returns counts: ['created' => n, 'updated' => n, 'skipped' => n].
     */
    public function importShows(?WhatnotChannel $channel = null, int $limit = 50, bool $debug = false, bool $withOrders = true, ?callable $onProgress = null): array
    {
        try {
            $rows = $this->fetchShows($limit, $debug, $channel?->whatnot_username, $onProgress);
        } catch (\Throwable $e) {
            ShowIngestionLog::create([
                'source'        => 'whatnot',
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
                'raw_payload'   => ['channel' => $channel?->name, 'limit' => $limit],
            ]);
            throw $e;
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;

        if ($onProgress) {
            $onProgress(sprintf('Fetched %d show(s) from Whatnot — importing…', count($rows)));
        }

        // Collect (Show, livestream id) pairs so we can batch-scrape their orders
        // in a single session after all shows are persisted.
        $orderTargets = [];

        foreach ($rows as $row) {
            if (empty($row['title']) && empty($row['show_date'])) {
                $skipped++;
                ShowIngestionLog::create([
                    'source'        => 'whatnot',
                    'status'        => 'failed',
                    'error_message' => 'Scraped row had no title or show_date — could not identify the show.',
                    'raw_payload'   => $row,
                ]);
                if ($onProgress) {
                    $onProgress('Skipped: row had no title or show date');
                }
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
                'start_time'            => $row['start_time'] ?? null,
                'end_time'              => $row['end_time'] ?? null,
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
                    'start_time', 'end_time',
                    'completed_earnings', 'avg_order_value', 'giveaway_spend', 'giveaways_count',
                    'buyers_count', 'first_time_buyers', 'returning_buyers', 'shares_count',
                    'max_concurrent_viewers', 'total_views', 'avg_order_rating',
                ]));

                // Attribution guard: this show is being returned by channel X's scrape
                // but was first attributed to a different channel. The Whatnot channel
                // switch is known to be unreliable, so flag it for manual review rather
                // than silently re-stamping (we keep the original channel_id).
                if ($channel && $existing->whatnot_channel_id
                    && (int) $existing->whatnot_channel_id !== (int) $channel->id) {
                    $updateFields['channel_attribution_suspect'] = true;
                }

                // Revision guard: a show past pending_review may already have deduction
                // lines and payouts calculated off its current numbers. If a later scrape
                // brings back different core financials, flag it rather than silently
                // overwriting the figures ops already reviewed/approved.
                if (in_array($existing->status, ['pending_approval', 'reconciled', 'closed'], true)) {
                    $changes = [];
                    foreach (['gross_revenue', 'whatnot_net', 'tips', 'units_sold'] as $field) {
                        if (! array_key_exists($field, $updateFields)) {
                            continue;
                        }
                        $old = (float) $existing->{$field};
                        $new = (float) $updateFields[$field];
                        if (abs($old - $new) > 0.01) {
                            $changes[] = "{$field}: {$old} → {$new}";
                        }
                    }

                    if (! empty($changes)) {
                        $updateFields['financials_revised_after_lock'] = true;
                        $updateFields['revision_notes'] = trim(
                            ($existing->revision_notes ? $existing->revision_notes . "\n" : '')
                            . now()->format('M j, Y g:ia') . ' — ' . implode('; ', $changes)
                        );
                    }
                }

                if (! empty($updateFields)) {
                    $existing->trackChanges($updateFields, 'whatnot_import');
                    $existing->update($updateFields);
                    $updated++;
                    ShowIngestionLog::create([
                        'show_id'     => $existing->id,
                        'source'      => 'whatnot',
                        'status'      => 'success',
                        'raw_payload' => $row,
                    ]);
                    if ($onProgress) {
                        $onProgress("Updated: \"{$existing->title}\" ({$lookupDate}) — " . implode(', ', array_keys($updateFields)));
                    }
                } else {
                    $skipped++;
                    if ($onProgress) {
                        $onProgress("Unchanged: \"{$existing->title}\" ({$lookupDate}) — already up to date");
                    }
                }
                $showModel = $existing;

                // Catches shows that slipped through a prior import without a
                // match (e.g. the streamer roster didn't have them yet at the
                // time) — try again on every re-scrape until one sticks.
                if ($showModel->streamers()->count() === 0) {
                    $showModel->detectStreamers();
                }
            } else {
                // show_date is NOT NULL with no default — skip creation rather than crash
                if (! $lookupDate) {
                    $skipped++;
                    ShowIngestionLog::create([
                        'source'        => 'whatnot',
                        'status'        => 'failed',
                        'error_message' => 'Scraped row had a title but no show_date (required) — show was not created.',
                        'raw_payload'   => $row,
                    ]);
                    if ($onProgress) {
                        $onProgress("Skipped: \"{$lookupTitle}\" has no show_date — cannot create");
                    }
                    continue;
                }
                // Set status to 'mapping' if units were sold (items need mapping), otherwise 'draft' (no items/test show)
                $status = ($payload['units_sold'] ?? 0) > 0 ? 'mapping' : 'draft';
                $show = Show::create(array_merge($payload, ['status' => $status, 'created_by' => auth()->id() ?? 1]));
                $show->detectStreamers();
                $created++;
                $showModel = $show;
                ShowIngestionLog::create([
                    'show_id'     => $show->id,
                    'source'      => 'whatnot',
                    'status'      => 'success',
                    'raw_payload' => $row,
                ]);
                if ($onProgress) {
                    $show->load('streamers');
                    $streamerNote = $show->streamers->isNotEmpty()
                        ? ' → matched streamer: ' . $show->streamers->pluck('name')->join(', ')
                        : '';
                    $onProgress("Created: \"{$show->title}\" ({$lookupDate}){$streamerNote}");
                }
            }

            // Auto-create the streamer's log entry for this show so they land
            // straight in the enrichment flow after import (one entry per show).
            $this->ensureStreamerLogEntry($showModel);

            // Record this show's livestream id for the batched order scrape.
            $liveId = $row['whatnot_live_id'] ?? $this->extractLiveIdFromUrl($row['detail_url'] ?? null);
            if ($withOrders && $liveId) {
                $orderTargets[] = ['show' => $showModel, 'live_id' => $liveId];
            }
        }

        if ($onProgress) {
            $onProgress("Shows done: {$created} created, {$updated} updated, {$skipped} skipped.");
        }

        $ordersCreated = 0;
        $shipmentsCreated = 0;
        if ($withOrders && ! empty($orderTargets)) {
            if ($onProgress) {
                $onProgress(sprintf('Scraping orders for %d show(s)…', count($orderTargets)));
            }
            $ordersCreated = $this->importOrdersForTargets($orderTargets, $channel?->whatnot_username, $debug, $onProgress);
            if ($onProgress) {
                $onProgress("Orders done: {$ordersCreated} order(s) created.");
            }

            // Also scrape shipment data for shows that have orders
            if ($onProgress) {
                $onProgress(sprintf('Scraping shipments for %d show(s)…', count($orderTargets)));
            }
            $showsWithOrders = collect($orderTargets)->pluck('show')->unique('id');
            $shipmentResults = $this->refreshShipmentsForShows($showsWithOrders, $channel?->whatnot_username, $debug);
            $shipmentsCreated = $shipmentResults['updated'] ?? 0;
            if ($onProgress) {
                $onProgress("Shipments done: {$shipmentsCreated} shipment(s) imported.");
            }
        }

        Log::info('WhatnotScraper import complete', [
            'channel'          => $channel?->name,
            'created'          => $created,
            'updated'          => $updated,
            'skipped'          => $skipped,
            'orders_created'   => $ordersCreated,
            'shipments_created' => $shipmentsCreated,
        ]);

        return compact('created', 'updated', 'skipped', 'ordersCreated', 'shipmentsCreated');
    }

    /**
     * Create a pending StreamerLogEntry for a show (one per show, keyed by the
     * unique show_id) so the streamer can enrich it after import. No-op when the
     * show has no detected streamer or an entry already exists.
     */
    private function ensureStreamerLogEntry(Show $show): void
    {
        $show->loadMissing('streamers');
        $streamer = $show->primaryStreamer();
        if (! $streamer) {
            return;
        }

        // One entry per show (schema enforces this); it's owned by the primary
        // streamer but visible/editable to every streamer on the show.
        StreamerLogEntry::firstOrCreate(
            ['show_id' => $show->id],
            ['streamer_id' => $streamer->id, 'status' => 'pending', 'gross_revenue' => $show->gross_revenue],
        );
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
    private function importOrdersForTargets(array $targets, ?string $channelUsername, bool $debug, ?callable $onProgress = null): int
    {
        $sources = [];
        $byKey   = [];
        foreach ($targets as $t) {
            $key = $t['show']->id;
            $sources[] = ['live_id' => $t['live_id'], 'show_key' => $key];
            $byKey[$key] = $t['show'];
        }

        try {
            $ordersByShow = $this->fetchOrdersForShows($sources, $channelUsername, $debug, $onProgress);
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

            // Safety net: order scraping trusts the ?source=<id> filter to scope
            // rows to this show. If that filter ever silently fails we'd get EVERY
            // order for the channel. The analytics "Orders" count (units_sold) is
            // our expected magnitude — if the scrape returns wildly more than that,
            // treat it as unfiltered and skip rather than corrupt the dataset.
            $expected = (int) ($show->units_sold ?? 0);
            if ($expected > 0 && count($rows) > ($expected * 2 + 100)) {
                Log::warning('WhatnotScraper: order count far exceeds expected — skipping (likely unfiltered)', [
                    'show_id'  => $show->id,
                    'scraped'  => count($rows),
                    'expected' => $expected,
                ]);
                if ($onProgress) {
                    $onProgress("Orders skipped for \"{$show->title}\" — scraped count far exceeds expected, likely unfiltered");
                }
                continue;
            }

            $res = $this->persistShowOrders($show, $rows);
            $ordersCreated += $res['created'];

            if ($onProgress) {
                $onProgress("Orders for \"{$show->title}\": {$res['created']} created, {$res['skipped']} skipped" . (($res['updated'] ?? 0) > 0 ? ", {$res['updated']} updated" : ''));
            }
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

        $this->withBrowserLock(function () use ($process, $onProgress) {
            if ($onProgress) {
                $this->streamProcess($process, $onProgress);
            } else {
                $process->run();
            }
        });

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

        $this->withBrowserLock(function () use ($process, $onProgress) {
            if ($onProgress) {
                $this->streamProcess($process, $onProgress);
            } else {
                $process->run();
            }
        });

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
        // The node side alone budgets 20s for the Seller Hub nav plus 8s settling
        // for network idle, so a 30s process budget left about two seconds for
        // Chromium to cold-start a persistent profile. On anything slower than a
        // warm dev box that expires mid-navigation, and the timeout surfaces as
        // "cookie test failed" — indistinguishable from cookies that are actually
        // bad, which is the one thing this command exists to tell you.
        $process = $this->makeProcess([
            'WHATNOT_MODE'  => 'cookie-test',
            'WHATNOT_DEBUG' => '0',
        ], timeout: 120);

        try {
            $this->withBrowserLock(fn () => $process->run());
        } catch (\Symfony\Component\Process\Exception\ProcessTimedOutException $e) {
            // Saying "cookie test failed" here would send you re-exporting a
            // session that was never shown to be bad. The browser simply never
            // finished starting.
            throw new \RuntimeException(
                'The browser did not finish starting within 120s, so the session was never actually tested. '
                . "This is a machine problem, not a cookie problem — check Chromium is installed (php artisan whatnot:setup-chromium) "
                . 'and that the box is not out of memory.'
            );
        }

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

        $this->withBrowserLock(fn () => $process->run());

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
    /**
     * Stop with the right explanation when the scraper reports a blocked page.
     *
     * Exit 3 means Whatnot answered with a bot challenge or a signed-out page
     * rather than the one asked for. That is a session problem, not a markup
     * one — and reporting it as "selectors didn't match", which is what
     * happened before this existed, sends whoever reads the log hunting for a
     * form that was never served.
     *
     * @throws \RuntimeException always, when the code is one this handles
     */
    protected function throwForExitCode(int $exitCode, string $stderr): void
    {
        $diagnostics = implode("\n", array_slice(
            array_values(array_filter(explode("\n", $stderr), fn ($l) => trim($l) !== '')),
            0,
            80,
        ));

        if ($exitCode === self::EXIT_AUTH_REQUIRED) {
            throw new \RuntimeException(
                "Whatnot session expired — sign in again.\n"
                . "The scraper was served a bot-protection challenge instead of the page, which is what "
                . "happens once the saved session lapses and it falls back to the login form.\n"
                . "Refresh it with: php artisan whatnot:login\n\n"
                . $diagnostics
            );
        }

        if ($exitCode === self::EXIT_RATE_LIMITED) {
            throw new \RuntimeException(
                "Whatnot is rate limiting this account. Wait and retry — running more often will not help.\n\n"
                . $diagnostics
            );
        }

        if ($exitCode === self::EXIT_SELECTOR_MISS) {
            throw new \RuntimeException(
                "Whatnot scraper: page selectors didn't match.\n"
                . "Whatnot changed their markup — update the SELECTORS object in scripts/whatnot-scraper.cjs.\n\n"
                . $diagnostics
            );
        }
    }

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
