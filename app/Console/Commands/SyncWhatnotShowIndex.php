<?php

namespace App\Console\Commands;

use App\Models\Show;
use App\Models\ShowIngestionLog;
use App\Models\WhatnotChannel;
use App\Services\WhatnotScraper;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\Process;

class SyncWhatnotShowIndex extends Command
{
    protected $signature = 'whatnot:sync-show-index
                            {--limit=200 : Retained for scheduler compatibility}
                            {--enrich=3 : Completed shows to enrich with analytics + shipments per run}
                            {--debug : Stream scraper diagnostics}';

    protected $description = 'Sync Current/Upcoming/Past Whatnot shows and incrementally enrich completed shows with analytics and shipments';

    private bool $skippedForLock = false;

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

        $maxFutureDays = max(30, min(365, (int) config('vortex.whatnot.max_upcoming_days', 120)));
        $futureCutoff = today()->addDays($maxFutureDays);

        $aliasIds = Schema::hasTable('whatnot_show_aliases')
            ? DB::table('whatnot_show_aliases')->pluck('duplicate_whatnot_show_id')->filter()->values()->all()
            : [];
        $aliasMap = array_fill_keys($aliasIds, true);

        $enrichLimit = max(0, min(10, (int) $this->option('enrich')));
        $enrichQuery = Show::query()
            ->where('whatnot_channel_id', $channel->id)
            ->whereNotNull('whatnot_show_id')
            ->whereDate('show_date', '<=', today())
            // Missing analytics OR missing shipments. It used to ask only about
            // the analytics columns, so the moment a show's figures arrived it
            // stopped being selected — and if its shipments had not come with
            // them, nothing ever went back for them. Shipments are fetched on
            // the same visit, so including them here costs no extra scraping.
            ->where(function ($q) {
                $q->whereNull('gross_revenue')
                    ->orWhereNull('completed_earnings')
                    ->orWhereNull('buyers_count')
                    ->orWhereNull('total_views')
                    ->orWhereNull('last_shipments_synced_at');
            })
            // Never-touched shows first, then newest. Without the first clause a
            // steady trickle of recent shows can keep the back catalogue at the
            // end of the queue indefinitely.
            ->orderByRaw('CASE WHEN last_analytics_synced_at IS NULL THEN 0 ELSE 1 END');

        if ($aliasIds !== []) {
            $enrichQuery->whereNotIn('whatnot_show_id', $aliasIds);
        }

        $enrichIds = $enrichQuery
            ->orderByDesc('show_date')
            ->limit($enrichLimit)
            ->pluck('whatnot_show_id')
            ->filter()
            ->values()
            ->all();

        $data = $this->scrape($enrichIds, $enrichLimit, (bool) $this->option('debug'));

        // Skipped is not done. Returning SUCCESS here made a run that never
        // started indistinguishable from one that found nothing to sync.
        if ($this->skippedForLock) {
            return RefreshRecentWhatnotShows::SKIPPED_LOCKED;
        }

        if ($data === null) {
            return self::FAILURE;
        }

        $counts = [
            'created' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'current' => 0,
            'upcoming' => 0,
            'past' => 0,
            'skipped' => 0,
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
                if (! $liveId) continue;

                if (isset($aliasMap[$liveId])) {
                    $counts['skipped']++;
                    if ($this->option('debug')) $this->line("[whatnot-index] skip alias {$liveId}");
                    continue;
                }

                $title = trim((string) ($row['title'] ?? '')) ?: null;
                $showDate = $this->parseDate($row['date'] ?? $row['show_date'] ?? null);
                if (! $title || ! $showDate) {
                    $counts['skipped']++;
                    continue;
                }

                $parsedDate = Carbon::parse($showDate);
                $rowKind = (string) ($row['kind'] ?? $kind);

                // Never allow malformed Whatnot dates to become database rows.
                // Past/current rows cannot legitimately point into the future,
                // and no automatic Whatnot row may exceed our configured future
                // scheduling horizon. This catches bad years even when a scraper
                // row is mislabeled as "past" rather than "upcoming".
                $invalidForState = $rowKind === 'past' && $parsedDate->gt(today());
                $invalidCurrent = $rowKind === 'current' && $parsedDate->gt(today()->addDay());
                $beyondFutureHorizon = $parsedDate->gt($futureCutoff);

                if ($invalidForState || $invalidCurrent || $beyondFutureHorizon) {
                    $counts['skipped']++;
                    if ($this->option('debug')) {
                        $reason = $beyondFutureHorizon
                            ? 'beyond future horizon'
                            : ($invalidForState ? 'future-dated past row' : 'future-dated current row');
                        $this->warn("[whatnot-index] rejected {$reason}: {$liveId} {$showDate} [{$rowKind}] {$title}");
                    }
                    continue;
                }

                $row['_parsed_show_date'] = $showDate;
                $allRows[$liveId] = $row;
                $counts[$kind]++;
            }
        }

        foreach ($allRows as $liveId => $row) {
            $title = trim((string) ($row['title'] ?? '')) ?: null;
            $showDate = $row['_parsed_show_date'] ?? $this->parseDate($row['date'] ?? $row['show_date'] ?? null);
            if (! $title || ! $showDate) continue;

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
                    'whatnot_channel_id' => $channel->id,
                    'source' => 'whatnot_show_index',
                    'status' => 'success',
                    'raw_payload' => array_merge($row, ['_channel_id' => $channel->id]),
                ]);
            }
        }

        foreach (($data['enriched'] ?? []) as $entry) {
            if (! is_array($entry) || ! ($liveId = $this->liveId($entry))) continue;
            if (isset($aliasMap[$liveId])) continue;

            $show = Show::query()->where('whatnot_show_id', $liveId)->first();
            if (! $show) continue;

            $metrics = $entry['analytics']['metrics'] ?? null;
            if (is_array($metrics) && $metrics !== []) {
                $analyticsPayload = $this->analyticsPayload($metrics);
                if ($analyticsPayload !== []) {
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
                    // Record the fetch on the column, not only inside the JSON
                    // blob. This job does the work every ten minutes and never
                    // said so, so anything reading the stamp — dueShows(), the
                    // backfill's outstanding count, the Ingestion page — saw
                    // hundreds of shows as permanently unfetched while their
                    // figures were sitting right there.
                    $show->setAttribute('last_analytics_synced_at', now());
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
                $show->raw_import_payload = array_merge($raw, [
                    '_shipment_stats' => $entry['shipments']['stats'] ?? [],
                    '_shipments_synced_at' => now()->toIso8601String(),
                ]);
                $show->setAttribute('last_shipments_synced_at', now());
                $show->last_synced_at = now();
                $show->save();
            }

            ShowIngestionLog::create([
                'show_id' => $show->id,
                'whatnot_channel_id' => $channel->id,
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
            ['Current', 'Upcoming', 'Past', 'Created', 'Updated', 'Skipped', 'Analytics', 'Shipments +', 'Shipments ↻'],
            [[
                $counts['current'], $counts['upcoming'], $counts['past'],
                $counts['created'], $counts['updated'], $counts['skipped'], $counts['analytics'],
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
        $lock = \App\Support\WhatnotBrowserLock::make(1800);

        // Only the process that actually took the lock may clean up after it.
        // This runs every ten minutes, and its finally block cleared the holder
        // PID whether or not it had the lock — so each time it queued behind
        // another job it erased that job's record, leaving the lock "held with
        // no holder PID". That is the state whatnot:unlock reports as an
        // interrupted run, and this command was manufacturing it on a timer.
        $held = false;

        try {
            // Say we are queued before going quiet: block() is silent, and a
            // scheduled run waiting behind another job looked like a hung one.
            if (! $lock->get()) {
                WhatnotScraper::announceLockWait();
                $lock->block((int) config('vortex.whatnot.browser_lock_wait', 1200));
            }

            $held = true;
            $process->run(function (string $type, string $buffer) use ($debug): void {
                if ($debug && $type === Process::ERR) $this->output->write($buffer);
            });
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException) {
            $this->warn('Whatnot show sync skipped: another browser job is still running.');
            $this->line('  <fg=gray>If nothing is actually running, the lock is stale — clear it with</>');
            $this->line('  <fg=gray>php artisan whatnot:unlock.</>');
            $this->skippedForLock = true;
            return null;
        } catch (\Throwable $e) {
            $this->error('Whatnot production sync crashed: ' . $e->getMessage());
            return null;
        } finally {
            if ($held) {
                try { $lock->release(); } catch (\Throwable) {}
            }
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
        ], $dims);
    }
}
