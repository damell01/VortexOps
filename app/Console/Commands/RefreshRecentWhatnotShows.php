<?php

namespace App\Console\Commands;

use App\Models\Show;
use App\Models\ShowIngestionLog;
use App\Models\WhatnotChannel;
use App\Services\WhatnotScraper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Process\Process;

class RefreshRecentWhatnotShows extends Command
{
    protected $signature = 'whatnot:refresh-recent
                            {--limit=8 : Maximum completed shows to refresh per run}
                            {--days=30 : Rolling completed-show refresh window}
                            {--debug : Stream scraper diagnostics}';

    protected $description = 'Refresh analytics and fully paginated shipments for recent completed Whatnot shows';

    public function handle(WhatnotScraper $scraper): int
    {
        $channel = WhatnotChannel::query()
            ->where('status', 'active')
            ->where('include_in_import', true)
            ->orderBy('id')
            ->first()
            ?? WhatnotChannel::query()->where('status', 'active')->orderBy('id')->first()
            ?? WhatnotChannel::query()->orderBy('id')->first();

        if (! $channel) {
            $this->error('No Whatnot channel exists in VortexOps.');
            return self::FAILURE;
        }

        $limit = max(1, min(20, (int) $this->option('limit')));
        $days = max(1, min(90, (int) $this->option('days')));
        $debug = (bool) $this->option('debug');

        $shows = $this->dueShows($channel->id, $days, $limit);
        if ($shows->isEmpty()) {
            $this->info("No completed Whatnot shows are due for refresh in the last {$days} days.");
            return self::SUCCESS;
        }

        $ids = $shows->pluck('whatnot_show_id')->filter()->values()->all();
        $this->line('Refreshing ' . count($ids) . ' show(s): ' . implode(', ', $ids));

        $data = $this->scrape($ids, $debug);
        if ($data === null) {
            return self::FAILURE;
        }

        $counts = [
            'selected' => count($ids),
            'analytics' => 0,
            'shipment_shows' => 0,
            'shipments_created' => 0,
            'shipments_updated' => 0,
            'shipments_skipped' => 0,
        ];

        foreach (($data['enriched'] ?? []) as $entry) {
            if (! is_array($entry)) continue;

            $liveId = $this->liveId($entry);
            if (! $liveId) continue;

            $show = Show::query()
                ->where('whatnot_channel_id', $channel->id)
                ->where('whatnot_show_id', $liveId)
                ->first();
            if (! $show) continue;

            $metrics = $entry['analytics']['metrics'] ?? null;
            if (is_array($metrics) && $metrics !== []) {
                $payload = $this->analyticsPayload($metrics);
                if ($payload !== []) {
                    if (in_array($show->status, ['pending_approval', 'reconciled', 'closed'], true)) {
                        $revisions = [];
                        foreach (['gross_revenue', 'whatnot_net', 'completed_earnings', 'units_sold'] as $field) {
                            if (! array_key_exists($field, $payload)) continue;
                            if ((string) $show->{$field} !== (string) $payload[$field]) {
                                $revisions[] = "{$field}: {$show->{$field}} → {$payload[$field]}";
                            }
                        }
                        if ($revisions !== []) {
                            $payload['financials_revised_after_lock'] = true;
                            $payload['revision_notes'] = trim(
                                ($show->revision_notes ? $show->revision_notes . "\n" : '')
                                . now()->format('M j, Y g:ia') . ' — ' . implode('; ', $revisions)
                            );
                        }
                    }

                    $show->trackChanges($payload, 'whatnot_spa_sync');
                    $show->fill($payload);
                    $raw = is_array($show->raw_import_payload) ? $show->raw_import_payload : [];
                    $show->raw_import_payload = array_merge($raw, [
                        '_analytics_metrics' => $metrics,
                        '_analytics_synced_at' => now()->toIso8601String(),
                    ]);
                    $show->setAttribute('last_analytics_synced_at', now());
                    $show->last_synced_at = now();
                    $show->save();
                    $counts['analytics']++;
                }
            }

            $shipmentRows = $entry['shipments']['rows'] ?? null;
            if (is_array($shipmentRows)) {
                $normalized = array_values(array_filter(array_map(
                    fn (array $row) => $this->normalizeShipment($row),
                    array_filter($shipmentRows, 'is_array')
                )));

                if ($normalized !== []) {
                    $result = $scraper->persistShipments($show, $normalized);
                    $counts['shipments_created'] += $result['created'] ?? 0;
                    $counts['shipments_updated'] += $result['updated'] ?? 0;
                    $counts['shipments_skipped'] += $result['skipped'] ?? 0;
                }

                $raw = is_array($show->raw_import_payload) ? $show->raw_import_payload : [];
                $show->raw_import_payload = array_merge($raw, [
                    '_shipment_stats' => $entry['shipments']['stats'] ?? [],
                    '_shipments_synced_at' => now()->toIso8601String(),
                    '_shipment_row_count' => count($shipmentRows),
                ]);
                $show->setAttribute('last_shipments_synced_at', now());
                $show->last_synced_at = now();
                $show->save();
                $counts['shipment_shows']++;
            }

            ShowIngestionLog::create([
                'show_id' => $show->id,
                'source' => 'whatnot_recent_refresh',
                'status' => 'success',
                'raw_payload' => [
                    'live_id' => $liveId,
                    'analytics' => $metrics,
                    'shipment_stats' => $entry['shipments']['stats'] ?? null,
                    'shipment_count' => is_array($shipmentRows) ? count($shipmentRows) : 0,
                    '_channel_id' => $channel->id,
                ],
            ]);
        }

        $this->info("Recent Whatnot refresh complete for {$channel->name}:");
        $this->table(
            ['Selected', 'Analytics', 'Shipment Shows', 'Shipments +', 'Shipments ↻', 'Unchanged'],
            [[
                $counts['selected'],
                $counts['analytics'],
                $counts['shipment_shows'],
                $counts['shipments_created'],
                $counts['shipments_updated'],
                $counts['shipments_skipped'],
            ]]
        );

        return self::SUCCESS;
    }

    private function dueShows(int $channelId, int $days, int $limit)
    {
        $windowStart = today()->subDays($days);
        $recentCutoff = now()->subMinutes(30);
        $olderCutoff = now()->subHours(6);

        return Show::query()
            ->where('whatnot_channel_id', $channelId)
            ->whereNotNull('whatnot_show_id')
            ->whereDate('show_date', '<=', today())
            ->where(function ($query) use ($windowStart, $recentCutoff, $olderCutoff) {
                // Missing data is always repairable, regardless of age.
                $query->whereNull('last_analytics_synced_at')
                    ->orWhereNull('last_shipments_synced_at')
                    // Last seven days: Whatnot metrics and delivery state can move quickly.
                    ->orWhere(function ($q) use ($recentCutoff) {
                        $q->whereDate('show_date', '>=', today()->subDays(7))
                            ->where(function ($stale) use ($recentCutoff) {
                                $stale->where('last_analytics_synced_at', '<=', $recentCutoff)
                                    ->orWhere('last_shipments_synced_at', '<=', $recentCutoff);
                            });
                    })
                    // Days 8 through configured window: refresh every six hours.
                    ->orWhere(function ($q) use ($windowStart, $olderCutoff) {
                        $q->whereDate('show_date', '>=', $windowStart)
                            ->whereDate('show_date', '<', today()->subDays(7))
                            ->where(function ($stale) use ($olderCutoff) {
                                $stale->where('last_analytics_synced_at', '<=', $olderCutoff)
                                    ->orWhere('last_shipments_synced_at', '<=', $olderCutoff);
                            });
                    })
                    // Older than the rolling window: only keep polling unresolved delivery state.
                    ->orWhereHas('shipments', fn ($shipments) => $shipments
                        ->whereRaw("LOWER(COALESCE(status, '')) <> 'delivered'"));
            })
            ->orderByRaw('CASE WHEN last_analytics_synced_at IS NULL OR last_shipments_synced_at IS NULL THEN 0 ELSE 1 END')
            ->orderByDesc('show_date')
            ->orderBy('last_analytics_synced_at')
            ->limit($limit)
            ->get();
    }

    private function scrape(array $ids, bool $debug): ?array
    {
        $env = [
            'WHATNOT_DEBUG' => $debug ? '1' : '0',
            'WHATNOT_ENRICH_IDS' => implode(',', $ids),
            'WHATNOT_ENRICH_LIMIT' => (string) count($ids),
        ];

        foreach ([
            'PLAYWRIGHT_BROWSERS_PATH' => config('vortex.whatnot.playwright_browsers_path') ?: '/opt/pw-browsers',
            'PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH' => config('vortex.whatnot.playwright_chromium_executable'),
            'WHATNOT_PROXY' => config('vortex.whatnot.proxy'),
        ] as $key => $value) {
            if ($value !== null && $value !== '') $env[$key] = (string) $value;
        }
        if (($headless = config('vortex.whatnot.headless')) !== null) {
            $env['WHATNOT_HEADLESS'] = $headless ? 'true' : 'false';
        }

        $command = [config('vortex.whatnot.node_bin', 'node'), base_path('scripts/whatnot-production-sync.cjs')];
        $headed = ($env['WHATNOT_HEADLESS'] ?? 'true') === 'false';
        if ($headed && ! getenv('DISPLAY') && is_readable(base_path('scripts/with-xvfb.sh'))) {
            $command = ['/bin/sh', base_path('scripts/with-xvfb.sh'), ...$command];
        }

        $process = new Process($command, base_path(), $env);
        $process->setTimeout(1200);
        $lock = Cache::lock('whatnot:browser', 1800);

        try {
            $lock->block(600);
            Cache::put('whatnot:browser:holder_pid', getmypid(), 1800);
            $process->run(function (string $type, string $buffer) use ($debug): void {
                if ($debug && $type === Process::ERR) $this->output->write($buffer);
            });
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException) {
            $this->warn('Recent Whatnot refresh skipped: another browser job is still running.');
            return [];
        } catch (\Throwable $e) {
            $this->error('Recent Whatnot refresh crashed: ' . $e->getMessage());
            return null;
        } finally {
            Cache::forget('whatnot:browser:holder_pid');
            try { $lock->release(); } catch (\Throwable) {}
        }

        if (! $process->isSuccessful()) {
            $this->error('Recent Whatnot refresh failed with exit code ' . $process->getExitCode() . '.');
            if (trim($process->getErrorOutput()) !== '') $this->line(trim($process->getErrorOutput()));
            return null;
        }

        $data = json_decode(trim($process->getOutput()), true);
        if (! is_array($data)) {
            $this->error('Recent Whatnot refresh returned invalid JSON.');
            return null;
        }

        return $data;
    }

    private function liveId(array $row): ?string
    {
        $candidate = $row['live_id'] ?? $row['whatnot_live_id'] ?? $row['id'] ?? null;
        if (is_string($candidate) && preg_match('/^[0-9a-f-]{36}$/i', $candidate)) return $candidate;
        $url = (string) ($row['open_url'] ?? $row['detail_url'] ?? $row['url'] ?? '');
        return preg_match('/([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})/i', $url, $m) ? $m[1] : null;
    }

    private function money(mixed $value): ?float
    {
        if (! filled($value)) return null;
        $clean = preg_replace('/[^0-9.\-]/', '', (string) $value);
        return $clean === '' ? null : (float) $clean;
    }

    private function integer(mixed $value): ?int
    {
        if (! filled($value)) return null;
        $clean = preg_replace('/[^0-9\-]/', '', (string) $value);
        return $clean === '' ? null : (int) $clean;
    }

    private function durationSeconds(mixed $value): ?int
    {
        if (! filled($value)) return null;
        $s = strtolower((string) $value);
        $hours = preg_match('/(\d+)\s*h/', $s, $m) ? (int) $m[1] : 0;
        $mins = preg_match('/(\d+)\s*m/', $s, $m) ? (int) $m[1] : 0;
        return ($hours || $mins) ? ($hours * 3600 + $mins * 60) : null;
    }

    private function analyticsPayload(array $m): array
    {
        return array_filter([
            'gross_revenue' => $this->money($m['Estimated Sales'] ?? null),
            'whatnot_net' => $this->money($m['Total Estimated Earnings'] ?? null),
            'completed_earnings' => $this->money($m['Completed Earnings'] ?? null),
            'units_sold' => $this->integer($m['Orders'] ?? null),
            'avg_order_value' => $this->money($m['Average Order Value'] ?? $m['AOV'] ?? null),
            'giveaway_spend' => $this->money($m['Giveaway Spend'] ?? null),
            'giveaways_count' => $this->integer($m['Giveaways'] ?? null),
            'buyers_count' => $this->integer($m['Buyers'] ?? null),
            'first_time_buyers' => $this->integer($m['First Time Buyers'] ?? null),
            'returning_buyers' => $this->integer($m['Returning Buyers'] ?? null),
            'shares_count' => $this->integer($m['Shares'] ?? null),
            'show_duration' => $this->durationSeconds($m['Show Duration'] ?? $m['Duration'] ?? null),
            'max_concurrent_viewers' => $this->integer($m['Max Concurrent Viewers'] ?? null),
            'total_views' => $this->integer($m['Total Views'] ?? null),
            'avg_order_rating' => $this->money($m['Average Order Rating'] ?? null),
        ], fn ($v) => $v !== null);
    }

    private function normalizeShipment(array $row): ?array
    {
        $tracking = trim((string) ($row['tracking'] ?? '')) ?: null;
        if (! $tracking) return null;

        $weight = strtolower(trim((string) ($row['weight'] ?? '')));
        $weightOz = 0.0;
        if (preg_match('/([0-9.]+)\s*lb/', $weight, $m)) $weightOz += (float) $m[1] * 16;
        if (preg_match('/([0-9.]+)\s*oz/', $weight, $m)) $weightOz += (float) $m[1];

        $dims = [];
        if (preg_match('/([0-9.]+)\s*[×x]\s*([0-9.]+)\s*[×x]\s*([0-9.]+)/u', (string) ($row['dimensions'] ?? ''), $m)) {
            $dims = ['box_length_in' => (float) $m[1], 'box_width_in' => (float) $m[2], 'box_height_in' => (float) $m[3]];
        }

        return array_merge([
            'tracking_number' => $tracking,
            'buyer' => $row['recipient'] ?? null,
            'quantity' => $this->integer($row['items'] ?? null),
            'weight_oz' => $weightOz > 0 ? $weightOz : null,
            'shipping_status_scraped' => strtolower(str_replace(' ', '_', trim((string) ($row['status'] ?? '')))) ?: null,
            'shipping_carrier' => $row['carrier'] ?? null,
            'raw_text' => implode(' | ', array_filter([
                $row['recipient'] ?? null,
                $row['order_date'] ?? null,
                $row['value'] ?? null,
                $row['weight'] ?? null,
                $row['dimensions'] ?? null,
                $row['requirements'] ?? null,
                $row['status'] ?? null,
                $row['carrier'] ?? null,
                $tracking,
            ])),
        ], $dims);
    }
}
