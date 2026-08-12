<?php

namespace App\Console\Commands;

use App\Models\WhatnotChannel;
use App\Services\WhatnotScraper;
use Illuminate\Console\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;

class ImportWhatnotShows extends Command
{
    protected $signature = 'whatnot:import
                            {--channel=    : WhatnotChannel name or ID — if omitted, imports all enabled channels}
                            {--limit=50    : Max number of shows to fetch per channel per run}
                            {--debug       : Save Playwright screenshots to /tmp for debugging selectors}
                            {--no-orders   : Skip scraping per-show orders (faster; imports show summaries only)}
                            {--discover    : 3-phase browser crawl — logs all Phoenix WS frames so you can read topic/event names}
                            {--ws-explore  : Direct Phoenix Channels probe — no DOM scraping, joins seller_hub:* topics for 20 s}';

    protected $description = 'Scrape completed shows from the Whatnot seller dashboard and import them';

    public function handle(WhatnotScraper $scraper): int
    {
        $limit      = (int) $this->option('limit');
        $debug      = (bool) $this->option('debug');
        $withOrders = ! (bool) $this->option('no-orders');

        // Discover mode: dump all API endpoints Whatnot calls when loading /seller/shows.
        // Run once to find the internal REST paths, then use those for direct API calls.
        if ($this->option('discover')) {
            $channel = null;
            if ($channelOpt = $this->option('channel')) {
                $channel = is_numeric($channelOpt)
                    ? WhatnotChannel::find($channelOpt)
                    : WhatnotChannel::where('name', $channelOpt)->orWhere('whatnot_username', $channelOpt)->first();
            }
            $progressLog = storage_path('logs/whatnot-discover-progress-' . date('Y-m-d-His') . '.log');
            $logHandle   = fopen($progressLog, 'w');

            $this->info('Running discover mode — navigating Seller Hub and capturing API endpoints…');
            $this->line("<fg=gray>Progress log: {$progressLog}</>");
            $this->line('');
            try {
                $json = app(WhatnotScraper::class)->runDiscover(
                    channel: $channel,
                    debug: true,
                    onProgress: function (string $line) use ($logHandle) {
                        $this->line("<fg=gray>  {$line}</>");
                        fwrite($logHandle, '[' . date('H:i:s') . '] ' . $line . "\n");
                        fflush($logHandle);
                    },
                );

                $summary = $json['summary'] ?? [];
                $this->line('');
                $this->info('── Discover Summary ──────────────────────────────────');
                $this->line("  Nav links found:      " . ($summary['nav_links_found'] ?? '?'));
                $this->line("  Pages visited:        " . ($summary['pages_visited'] ?? '?'));
                $this->line("  Unique API endpoints: " . ($summary['unique_api_endpoints'] ?? '?'));
                $this->line("  Total API calls:      " . ($summary['total_api_calls'] ?? '?'));
                $this->line('');

                $this->info('── Nav Links ─────────────────────────────────────────');
                foreach ($json['nav_links'] ?? [] as $link) {
                    $this->line("  " . str_pad($link['label'] ?? '', 30) . " → " . $link['href']);
                }
                $this->line('');

                $this->info('── Unique API Endpoints ──────────────────────────────');
                foreach ($json['unique_endpoints'] ?? [] as $i => $ep) {
                    $method = $ep['method'] ?? 'GET';
                    $this->line("  [" . str_pad((string)($i + 1), 3) . "] {$method} " . $ep['url']);
                    if (!empty($ep['keys'])) {
                        $this->line("         Keys: " . implode(', ', array_slice($ep['keys'], 0, 10)));
                    }
                    $this->line("         Page: " . ($ep['from_page'] ?? ''));
                    $this->line('');
                }

                // Also write the raw JSON to a file for full review
                $outFile = storage_path('logs/whatnot-discover-' . date('Y-m-d-His') . '.json');
                file_put_contents($outFile, json_encode($json, JSON_PRETTY_PRINT));
                $this->info("Full JSON saved to: {$outFile}");

            } catch (\RuntimeException $e) {
                fwrite($logHandle, '[' . date('H:i:s') . '] ERROR: ' . $e->getMessage() . "\n");
                $this->error($e->getMessage());
            } finally {
                fclose($logHandle);
            }
            return self::SUCCESS;
        }

        // ws-explore mode: directly probe Phoenix Channels WebSocket to learn topic/event names
        if ($this->option('ws-explore')) {
            $channel = null;
            if ($channelOpt = $this->option('channel')) {
                $channel = is_numeric($channelOpt)
                    ? WhatnotChannel::find($channelOpt)
                    : WhatnotChannel::where('name', $channelOpt)->orWhere('whatnot_username', $channelOpt)->first();
            }
            $progressLog = storage_path('logs/whatnot-ws-explore-' . date('Y-m-d-His') . '.log');
            $logHandle   = fopen($progressLog, 'w');

            $this->info('Running ws-explore — connecting directly to Whatnot Phoenix Channels WebSocket…');
            $this->line("<fg=gray>Progress log: {$progressLog}</>");
            $this->line('');
            try {
                $json = app(WhatnotScraper::class)->runWsExplore(
                    channel: $channel,
                    onProgress: function (string $line) use ($logHandle) {
                        $this->line("<fg=gray>  {$line}</>");
                        fwrite($logHandle, '[' . date('H:i:s') . '] ' . $line . "\n");
                        fflush($logHandle);
                    },
                );

                $this->line('');
                $this->info('── WS-Explore Summary ────────────────────────────────');
                $this->line("  Total frames:   " . ($json['total_messages'] ?? 0));
                $topicsJoined = $json['topics_joined'] ?? [];
                $this->line("  Topics joined:  " . (count($topicsJoined) ? implode(', ', $topicsJoined) : 'none'));
                $this->line('');

                // Outbound phx_join frames — show real topic names the app uses
                $sentJoins = array_values(array_filter($json['topics'] ?? [], fn($t) => ($t['dir'] ?? '') === 'sent' && $t['event'] === 'phx_join'));
                if ($sentJoins) {
                    $this->info('── Topics the App Joined (phx_join sent) ─────────────');
                    foreach ($sentJoins as $t) {
                        $this->line("  " . $t['topic']);
                    }
                    $this->line('');
                }

                // HTTP API calls — likely where the shows data actually lives
                $httpCaptures = $json['http_captures'] ?? [];
                if ($httpCaptures) {
                    $this->info('── HTTP API Calls (' . count($httpCaptures) . ') ─────────────────────────');
                    foreach ($httpCaptures as $h) {
                        $method  = $h['method'] ?? 'GET';
                        $url     = $h['url'] ?? '';
                        $status  = $h['status'] ?? '?';
                        $this->line("  {$method} {$status} {$url}");
                        if (!empty($h['body'])) {
                            $preview = is_array($h['body']) ? json_encode($h['body'], JSON_UNESCAPED_SLASHES) : (string) $h['body'];
                            $this->line("         " . substr($preview, 0, 400));
                        }
                        $this->line('');
                    }
                } else {
                    $this->line('  (no HTTP API calls captured — shows may not have loaded in temp browser)');
                    $this->line('');
                }

                // Inbound WS frames grouped by topic
                $recvTopics = array_values(array_filter($json['topics'] ?? [], fn($t) => ($t['dir'] ?? 'recv') === 'recv'));
                if ($recvTopics) {
                    $this->info('── WebSocket Frames by Topic ─────────────────────────');
                    foreach ($recvTopics as $t) {
                        $topic  = $t['topic'];
                        $event  = $t['event'];
                        $count  = $t['count'];
                        $sample = $t['sample'] ?? null;

                        if ($event === 'phx_reply') {
                            $status = $sample['status'] ?? '?';
                            $icon   = $status === 'ok' ? '<fg=green>✓</>' : '<fg=yellow>·</>';
                            $this->line("  [{$count}x] {$icon} {$topic}/{$event} status={$status}");
                            if (!empty($sample['response'])) {
                                $resp = json_encode($sample['response'], JSON_UNESCAPED_SLASHES);
                                $this->line("         response: " . substr($resp, 0, 300));
                            }
                        } else {
                            $this->line("  [{$count}x] {$topic}/{$event}");
                            if ($sample !== null) {
                                $preview = json_encode($sample, JSON_UNESCAPED_SLASHES);
                                $this->line("         payload: " . substr($preview, 0, 400));
                            }
                        }
                        $this->line('');
                    }
                }

                $outFile = storage_path('logs/whatnot-ws-explore-' . date('Y-m-d-His') . '.json');
                file_put_contents($outFile, json_encode($json, JSON_PRETTY_PRINT));
                $this->info("Full JSON saved to: {$outFile}");

            } catch (\RuntimeException $e) {
                fwrite($logHandle, '[' . date('H:i:s') . '] ERROR: ' . $e->getMessage() . "\n");
                $this->error($e->getMessage());
            } finally {
                fclose($logHandle);
            }
            return self::SUCCESS;
        }

        // Auto-cleanup any stale locks before we run
        $this->cleanupStaleLock();

        if ($channelOpt = $this->option('channel')) {
            // Single-channel mode
            $channel = is_numeric($channelOpt)
                ? WhatnotChannel::find($channelOpt)
                : WhatnotChannel::where('name', $channelOpt)->orWhere('whatnot_username', $channelOpt)->first();

            if (! $channel) {
                $this->error("Channel not found: {$channelOpt}");
                return self::FAILURE;
            }

            $this->info("Importing channel: {$channel->name} (@{$channel->whatnot_username})…");

            try {
                $result = $scraper->importShows(
                    channel: $channel,
                    limit: $limit,
                    debug: $debug,
                    withOrders: $withOrders,
                    onProgress: fn (string $line) => $this->line("  <fg=gray>" . OutputFormatter::escape($line) . "</>"),
                );
            } catch (\RuntimeException $e) {
                $this->error($e->getMessage());
                $this->printTroubleshootingHints($e->getMessage());
                return self::FAILURE;
            }

            $this->info("Import complete:");
            $this->table(
                ['Created', 'Updated', 'Skipped', 'Orders'],
                [[$result['created'], $result['updated'], $result['skipped'], $result['ordersCreated'] ?? 0]]
            );

        } else {
            // All-enabled-channels mode (default)
            $channels = WhatnotChannel::where('include_in_import', true)->where('status', 'active')->get();

            if ($channels->isEmpty()) {
                $this->warn('No active channels with "Include in Import" enabled.');
                $this->line('Enable channels in Settings → Whatnot Channels, or run with --channel=NAME for a specific one.');
                return self::FAILURE;
            }

            $this->info("Importing {$channels->count()} channel(s): " . $channels->pluck('name')->join(', ') . '…');

            $totals = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'orders' => 0];
            $rows   = [];

            foreach ($channels as $channel) {
                $this->line("  → {$channel->name} (@{$channel->whatnot_username})");
                try {
                    $result = $scraper->importShows(
                        channel: $channel,
                        limit: $limit,
                        debug: $debug,
                        withOrders: $withOrders,
                        onProgress: fn (string $line) => $this->line("    <fg=gray>" . OutputFormatter::escape($line) . "</>"),
                    );
                    $orders = $result['ordersCreated'] ?? 0;
                    $totals['created'] += $result['created'];
                    $totals['updated'] += $result['updated'];
                    $totals['skipped'] += $result['skipped'];
                    $totals['orders']  += $orders;
                    $rows[] = [$channel->name, $result['created'], $result['updated'], $result['skipped'], $orders];
                } catch (\RuntimeException $e) {
                    $this->error("  Channel \"{$channel->name}\" failed: " . $e->getMessage());
                    $this->printTroubleshootingHints($e->getMessage());
                    $rows[] = [$channel->name, 'ERROR', 'ERROR', 'ERROR', $e->getMessage()];
                }
            }

            $this->newLine();
            $this->info('Import complete:');
            $this->table(['Channel', 'Created', 'Updated', 'Skipped', 'Orders'], $rows);
            $this->line("Total: {$totals['created']} created, {$totals['updated']} updated, {$totals['skipped']} skipped, {$totals['orders']} orders");
        }

        return self::SUCCESS;
    }

    private function printTroubleshootingHints(string $message): void
    {
        if (str_contains($message, 'WHATNOT_EMAIL')) {
            $this->line('');
            $this->line('Add the following to your .env:');
            $this->line('  WHATNOT_EMAIL=your@email.com');
            $this->line('  WHATNOT_PASSWORD=yourpassword');
        }

        if (str_contains($message, 'selectors')) {
            $this->line('');
            $this->line('Re-run with --debug to capture screenshots:');
            $this->line('  php artisan whatnot:import --debug');
            $this->line('Screenshots saved to /tmp/whatnot-debug-*.png');
        }
    }

    private function cleanupStaleLock(): void
    {
        $pid = \Illuminate\Support\Facades\Cache::get('whatnot:browser:holder_pid');

        if ($pid && ! $this->pidIsAlive((int) $pid)) {
            \Illuminate\Support\Facades\Cache::lock('whatnot:browser')->forceRelease();
            \Illuminate\Support\Facades\Cache::forget('whatnot:browser:holder_pid');
            $this->info("🔓 Cleaned up stale lock (PID {$pid} not alive)");
        }
    }

    private function pidIsAlive(int $pid): bool
    {
        return is_dir("/proc/{$pid}");
    }
}
