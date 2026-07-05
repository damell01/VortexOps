<?php

namespace App\Services;

use App\Models\Show;
use App\Models\WhatnotChannel;
use App\Models\WhatnotShowOrder;
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
        [$email, $password] = $this->resolveCredentials();

        $process = $this->makeProcess([
            'WHATNOT_EMAIL'    => $email,
            'WHATNOT_PASSWORD' => $password,
            'WHATNOT_MODE'     => 'test',
            'WHATNOT_DEBUG'    => $debug ? '1' : '0',
        ], timeout: 60);

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
        [$email, $password] = $this->resolveCredentials();

        $env = [
            'WHATNOT_EMAIL'    => $email,
            'WHATNOT_PASSWORD' => $password,
            'WHATNOT_MODE'     => 'analytics',
            'WHATNOT_LIMIT'    => (string) $limit,
            'WHATNOT_DEBUG'    => $debug ? '1' : '0',
        ];

        if ($channelUsername) {
            $env['WHATNOT_CHANNEL_NAME'] = $channelUsername;
        }

        $process = $this->makeProcess($env);
        $process->run();

        $stderr = trim($process->getErrorOutput());
        $stdout = trim($process->getOutput());

        if ($stderr) {
            Log::channel('stack')->warning('WhatnotScraper stderr', ['output' => $stderr, 'channel' => $channelUsername]);
        }

        if ($process->getExitCode() === 2) {
            throw new \RuntimeException(
                "Whatnot scraper: analytics selectors didn't match the page. " .
                "Set WHATNOT_DEBUG=1 and re-run to capture screenshots. " .
                "Check logs for a PAGE_SNAPSHOT to update scripts/whatnot-scraper.cjs."
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
     * Scrape the order/lot list for a specific show detail URL.
     *
     * @throws \RuntimeException on scraper failures or selector misses
     */
    public function fetchShowOrders(string $showUrl, bool $debug = false): array
    {
        [$email, $password] = $this->resolveCredentials();

        $process = $this->makeProcess([
            'WHATNOT_EMAIL'    => $email,
            'WHATNOT_PASSWORD' => $password,
            'WHATNOT_MODE'     => 'show-orders',
            'WHATNOT_SHOW_URL' => $showUrl,
            'WHATNOT_DEBUG'    => $debug ? '1' : '0',
        ]);

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
        if (! $show->detail_url) {
            throw new \RuntimeException("Show #{$show->id} has no detail_url — run a show list import first to capture the Whatnot show URL.");
        }

        $rows    = $this->fetchShowOrders($show->detail_url, $debug);
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

        Log::info('WhatnotScraper importShowOrders complete', [
            'show_id' => $show->id,
            'created' => $created,
            'skipped' => $skipped,
        ]);

        return compact('created', 'skipped');
    }

    protected function makeProcess(array $env, int $timeout = 180): Process
    {
        $process = new Process([$this->nodeBin, $this->scriptPath], null, $env);
        $process->setTimeout($timeout);
        return $process;
    }

    /**
     * Fetch shows and upsert them into the shows table for one channel.
     * Returns counts: ['created' => n, 'updated' => n, 'skipped' => n].
     */
    public function importShows(?WhatnotChannel $channel = null, int $limit = 50, bool $debug = false): array
    {
        $rows = $this->fetchShows($limit, $debug, $channel?->whatnot_username);

        $created = 0;
        $updated = 0;
        $skipped = 0;

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
            } else {
                $show = Show::create(array_merge($payload, ['status' => 'draft', 'created_by' => auth()->id() ?? 1]));
                $show->detectStreamers();
                $created++;
            }
        }

        Log::info('WhatnotScraper import complete', [
            'channel'  => $channel?->name,
            'created'  => $created,
            'updated'  => $updated,
            'skipped'  => $skipped,
        ]);

        return compact('created', 'updated', 'skipped');
    }

    /**
     * Import shows from all channels that have include_in_import = true.
     * Returns aggregated counts across all channels.
     */
    public function importAllEnabledChannels(int $limit = 50, bool $debug = false): array
    {
        $channels = WhatnotChannel::where('include_in_import', true)
            ->where('status', 'active')
            ->get();

        if ($channels->isEmpty()) {
            Log::warning('WhatnotScraper importAllEnabledChannels: no active channels with include_in_import=true');
            return ['created' => 0, 'updated' => 0, 'skipped' => 0, 'channels' => 0];
        }

        $totals = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        foreach ($channels as $channel) {
            Log::info("WhatnotScraper: importing channel \"{$channel->name}\" ({$channel->whatnot_username})");

            try {
                $result = $this->importShows($channel, $limit, $debug);
                $totals['created'] += $result['created'];
                $totals['updated'] += $result['updated'];
                $totals['skipped'] += $result['skipped'];
            } catch (\RuntimeException $e) {
                Log::error("WhatnotScraper: channel \"{$channel->name}\" failed — {$e->getMessage()}");
            }
        }

        $totals['channels'] = $channels->count();
        return $totals;
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
