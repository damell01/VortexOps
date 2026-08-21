<?php

namespace App\Console\Commands;

use App\Models\Show;
use App\Models\ShowIngestionLog;
use App\Models\WhatnotChannel;
use App\Services\WhatnotScraper;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Process\Process;

class SyncWhatnotShowIndex extends Command
{
    protected $signature = 'whatnot:sync-show-index
                            {--limit=200 : Retained for scheduler compatibility}
                            {--enrich=3 : Completed shows to enrich with analytics + shipments per run}
                            {--debug : Stream scraper diagnostics}';

    protected $description = 'Sync Current/Upcoming/Past Whatnot shows and incrementally enrich completed shows with analytics and shipments';

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

        $enrichLimit = max(0, min(10, (int) $this->option('enrich')));
        $enrichIds = Show::query()
            ->where('whatnot_channel_id', $channel->id)
            ->whereNotNull('whatnot_show_id')
            ->whereDate('show_date', '<=', today())
            ->where(function ($q) {
                $q->whereNull('gross_revenue')
                    ->orWhereNull('completed_earnings')
                    ->orWhereNull('buyers_count')
                    ->orWhereNull('total_views');
            })
            ->orderByDesc('show_date')
            ->limit($enrichLimit)
            ->pluck('whatnot_show_id')
            ->filter()
            ->values()
            ->all();

        $data = $this->scrape($enrichIds, $enrichLimit, (bool) $this->option('debug'));
        if ($data === null) {
            return self::FAILURE;
        }

        $counts = [
            'created' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'current' => count($data['current'] ?? []),
            'upcoming' => count($data['upcoming'] ?? []),
            'past' => count($data['past'] ?? []),
            'analytics' => 0,
            'shipments_created' => 0,
            'shipments_updated' => 0,
            'shipments_skipped' => 0,
        ];

        $allRows = [];
        foreach (['current', 'upcoming', 'past'] as $kind) {
            foreach (($data[$kind] ?? []) as $row) {
                if (! is_array($row)) continue;
                $row['kind'] = $row['kind'] ?? $kind;
                $liveId = $this->liveId($row);
                if ($liveId) {
                    // Past wins over Upcoming wins over Current if Whatnot happens
                    // to return the same UUID in more than one transition window.
                    $allRows[$liveId] = $row;
                }
            }
        }

        foreach ($allRows as $liveId => $row) {
            $title = trim((string) ($row['title'] ?? '')) ?: null;
            $showDate = $this->parseDate($row['date'] ?? $row['show_date'] ?? null);
            if (! $title || ! $showDate) {
                continue;
            }

            $show = Show::query()
                ->where('whatnot_show_id', $liveId)
                ->orWhere('detail_url', 'like', "%{$liveId}%")
                ->first();

            $existingRaw = is_array($show?->raw_import_payload) ? $show->raw_import_payload : [];
            $payload = [
                'whatnot_channel_id' => $show?->whatnot_channel_id ?: $channel->id,
                'whatnot_show_id' => $liveId,
                'title' => $title,
                'show_date' => $showDate,
                'start_time' => $this->parseStartTime($showDate, $row['time'] ?? null),
                'detail_url' => 'https://www.whatnot.com/dashboard/live/' . $liveId,
                'last_synced_at' => now(),
                'import_source' => 'auto_whatnot',
                'raw_import_payload' => array_merge($existingRaw, [
                    '_show_index' => $row,
                    '_show_state' => $row['kind'] ?? null,
                    '_channel_id' => $channel->id,
                    '_index_synced_at' => now()->toIso8601String(),
                ]),
            ];

            if ($show) {
                $show->fill($payload);
                if ($show->isDirty()) {
                    $show->save();
                    $counts['updated']++;
                } else {
                    $counts['unchanged']++;
                }
            } else {
                $show = Show::create(array_merge($payload, [
                    'status' => 'draft',
                    'created_by' => auth()->id() ?? 1,
                ]));
                $show->detectStreamers();
                $counts['created']++;
                ShowIngestionLog::create([
                    'show_id' => $show->id,
                    'source' => 'whatnot_show_index',
                    'status' => 'success',
                    'raw_payload' => array_merge($row, ['_channel_id' => $channel->id]),
                ]);
            }
        }

        foreach (($data['enriched'] ?? []) as $entry) {
            if (! is_array($entry) || ! ($liveId = $this->liveId($entry))) {
                continue;
            }

            $show = Show::query()->where('whatnot_show_id', $liveId)->first();
            if (! $show) {
                continue;
            }

            $metrics = $entry['analytics']['metrics'] ?? null;
            if (is_array($metrics) && $metrics !== []) {
                $analyticsPayload = $this->analyticsPayload($metrics);
                if ($analyticsPayload !== []) {
                    // Preserve the review/reconciliation safety behavior from the old
                    // analytics importer: flag later financial revisions after lock.
                    if (in_array($show->status, ['pending_approval', 'reconciled', 'closed'], true)) {
                        $changes = [];
                        foreach (['gross_revenue', 'whatnot_net', 'units_sold'] as $field) {
                            if (! array_key_exists($field, $analyticsPayload)) continue;
                            if (abs((float) $show->{$field} - (float) $analyticsPayload[$field]) > 0.01) {
                                $changes[] = "{$field}: {$show->{$field}} → {$analyticsPayload[$field]}";
                            }
                        }
                        if ($changes !== []) {
                            $analyticsPayload['financials_revised_after_lock'] = true;
                            $analyticsPayload['revision_notes'] = trim(
                                ($show->revision_notes ? $show->revision_notes . "\n" : '')
                                . now()->format('M j, Y g:ia') . ' — ' . implode('; ', $changes)
                            );
                        }
                    }

                    $show->trackChanges($analyticsPayload, 'whatnot_spa_sync');
                    $show->fill($analyticsPayload);
                    if ($show->status === 'draft' && (int) ($analyticsPayload['units_sold'] ?? 0) > 0) {
                        $show->status = 'mapping';
                    }
                    $raw = is_array($show->raw_import_payload) ? $show->raw_import_payload : [];
                    $show->raw_import_payload = array_merge($raw, [
                        '_analytics_metrics' => $metrics,
                        '_analytics_synced_at' => now()->toIso8601String(),
                    ]);
                    $show->last_synced_at = now();
                    $show->save();
                    $counts['analytics']++;
                }
            }

            $shipmentRows = $entry['shipments']['rows'] ?? null;
            if (is_array($shipmentRows) && $shipmentRows !== []) {
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
                $show->update([
                    'raw_import_payload' => array_merge($raw, [
                        '_shipment_stats' => $entry['shipments']['stats'] ?? [],
                        '_shipments_synced_at' => now()->toIso8601String(),
                    ]),
                    'last_synced_at' => now(),
                ]);
            }

            ShowIngestionLog::create([
                'show_id' => $show->id,
                'source' => 'whatnot_spa_enrichment',
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

        $this->info("Whatnot show sync complete for {$channel->name}:");
        $this->table(
            ['Current', 'Upcoming', 'Past', 'Created', 'Updated', 'Analytics', 'Shipments +', 'Shipments ↻'],
            [[
                $counts['current'], $counts['upcoming'], $counts['past'],
                $counts['created'], $counts['updated'], $counts['analytics'],
                $counts['shipments_created'], $counts['shipments_updated'],
            ]]
        );

        return self::SUCCESS;
    }

    private function scrape(array $enrichIds, int $enrichLimit, bool $debug): ?array
    {
        $env = [
            'WHATNOT_DEBUG' => $debug ? '1' : '0',
            'WHATNOT_ENRICH_IDS' => implode(',', $enrichIds),
            'WHATNOT_ENRICH_LIMIT' => (string) $enrichLimit,
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
        $process->setTimeout(900);
        $lock = Cache::lock('whatnot:browser', 1800);

        try {
            $lock->block(600);
            Cache::put('whatnot:browser:holder_pid', getmypid(), 1800);
            $process->run(function (string $type, string $buffer) use ($debug): void {
                if ($debug && $type === Process::ERR) $this->output->write($buffer);
            });
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException) {
            $this->warn('Whatnot show sync skipped: another browser job is still running.');
            return [];
        } catch (\Throwable $e) {
            $this->error('Whatnot production sync crashed: ' . $e->getMessage());
            return null;
        } finally {
            Cache::forget('whatnot:browser:holder_pid');
            try { $lock->release(); } catch (\Throwable) {}
        }

        if (! $process->isSuccessful()) {
            $this->error('Whatnot production sync failed with exit code ' . $process->getExitCode() . '.');
            if (trim($process->getErrorOutput()) !== '') $this->line(trim($process->getErrorOutput()));
            return null;
        }

        $data = json_decode(trim($process->getOutput()), true);
        if (! is_array($data)) {
            $this->error('Whatnot production sync returned invalid JSON.');
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

    private function parseDate(mixed $value): ?string
    {
        if (! filled($value)) return null;
        try {
            $s = trim((string) $value);
            return preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', $s)
                ? Carbon::createFromFormat('n/j/Y', $s)->toDateString()
                : Carbon::parse($s)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseStartTime(string $date, mixed $time): ?string
    {
        if (! filled($time)) return null;
        try {
            return Carbon::parse($date . ' ' . trim((string) $time), 'America/Chicago')->toDateTimeString();
        } catch (\Throwable) {
            return null;
        }
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
                $row['recipient'] ?? null, $row['order_date'] ?? null, $row['value'] ?? null,
                $row['weight'] ?? null, $row['dimensions'] ?? null, $row['requirements'] ?? null,
                $row['status'] ?? null, $row['carrier'] ?? null, $tracking,
            ])),
            'raw_shipment_row' => $row,
        ], $dims);
    }
}
