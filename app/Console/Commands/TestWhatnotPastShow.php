<?php

namespace App\Console\Commands;

use App\Services\WhatnotScraper;
use Illuminate\Console\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;

class TestWhatnotPastShow extends Command
{
    protected $signature = 'whatnot:test-past-show
                            {live-id=a0a97cbb-097e-4c1f-9174-98ad66937e14 : Known completed Whatnot livestream UUID}
                            {--debug : Stream scraper diagnostics}';

    protected $description = 'Test one known completed Whatnot show independently for analytics, orders, and shipments';

    public function handle(WhatnotScraper $scraper): int
    {
        $liveId = trim((string) $this->argument('live-id'));
        $debug = (bool) $this->option('debug');

        if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $liveId)) {
            $this->error('live-id must be a valid UUID.');
            return self::FAILURE;
        }

        $progress = fn (string $line) => $this->line('  <fg=gray>' . OutputFormatter::escape($line) . '</>');

        $this->info("Testing completed Whatnot show: {$liveId}");
        $this->line('Each surface is tested independently so one failure does not hide the others.');
        $this->newLine();

        $analyticsOk = false;
        $ordersOk = false;
        $shipmentsOk = false;

        // 1) Analytics
        $this->info('1) Analytics');
        try {
            $rows = $scraper->fetchShows(
                limit: 1,
                debug: $debug,
                channelUsername: null,
                onProgress: $progress,
                seedLiveId: $liveId,
            );

            $row = $rows[0] ?? null;
            if (! is_array($row)) {
                $this->warn('  No analytics row returned.');
            } else {
                $analyticsFields = [
                    'title' => $row['title'] ?? null,
                    'show_date' => $row['show_date'] ?? null,
                    'estimated_sales' => $row['gross_revenue'] ?? null,
                    'total_estimated_earnings' => $row['whatnot_net'] ?? null,
                    'completed_earnings' => $row['completed_earnings'] ?? null,
                    'orders' => $row['units_sold'] ?? null,
                    'aov' => $row['avg_order_value'] ?? null,
                    'giveaway_spend' => $row['giveaway_spend'] ?? null,
                    'buyers' => $row['buyers_count'] ?? null,
                    'shares' => $row['shares_count'] ?? null,
                    'duration' => $row['show_duration'] ?? null,
                    'max_concurrent_viewers' => $row['max_concurrent_viewers'] ?? null,
                    'total_views' => $row['total_views'] ?? null,
                ];

                foreach ($analyticsFields as $field => $value) {
                    $this->line(sprintf('  %-28s %s', $field . ':', $value === null ? '—' : (string) $value));
                }

                $analyticsOk = collect($analyticsFields)
                    ->except(['title', 'show_date'])
                    ->contains(fn ($value) => $value !== null);

                $analyticsOk
                    ? $this->info('  ✓ Analytics metrics returned.')
                    : $this->warn('  ✗ Show row returned, but analytics metrics are empty.');
            }
        } catch (\Throwable $e) {
            $this->warn('  ✗ Analytics failed: ' . $e->getMessage());
        }

        $this->newLine();

        // 2) Orders
        $this->info('2) Orders');
        try {
            $map = $scraper->fetchOrdersForShows(
                [['live_id' => $liveId, 'show_key' => 'past-test']],
                channelUsername: null,
                debug: $debug,
                onProgress: $progress,
            );
            $orders = $map['past-test'] ?? [];
            $this->line('  Rows returned: ' . count($orders));
            if ($orders !== []) {
                $this->line('  First order: ' . json_encode($orders[0], JSON_UNESCAPED_SLASHES));
                $ordersOk = true;
                $this->info('  ✓ Orders returned for this past show.');
            } else {
                $this->warn('  ✗ No order rows returned.');
            }
        } catch (\Throwable $e) {
            $this->warn('  ✗ Orders failed: ' . $e->getMessage());
        }

        $this->newLine();

        // 3) Shipments
        $this->info('3) Shipments');
        try {
            $map = $scraper->fetchShipmentsForShows(
                [['live_id' => $liveId, 'show_key' => 'past-test']],
                channelUsername: null,
                debug: $debug,
                onProgress: $progress,
            );
            $shipments = $map['past-test'] ?? [];
            $this->line('  Rows returned: ' . count($shipments));
            if ($shipments !== []) {
                $this->line('  First shipment: ' . json_encode($shipments[0], JSON_UNESCAPED_SLASHES));
                $shipmentsOk = true;
                $this->info('  ✓ Shipment data returned for this past show.');
            } else {
                $this->warn('  ✗ No shipment rows returned.');
            }
        } catch (\Throwable $e) {
            $this->warn('  ✗ Shipments failed: ' . $e->getMessage());
        }

        $this->newLine();
        $this->info('Past-show test summary');
        $this->table(
            ['Surface', 'Result'],
            [
                ['Analytics / revenue / viewers', $analyticsOk ? 'PASS' : 'FAIL'],
                ['Orders', $ordersOk ? 'PASS' : 'FAIL'],
                ['Shipments', $shipmentsOk ? 'PASS' : 'FAIL'],
            ]
        );

        return ($analyticsOk && $ordersOk && $shipmentsOk) ? self::SUCCESS : self::FAILURE;
    }
}
