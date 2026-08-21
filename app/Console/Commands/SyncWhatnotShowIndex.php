<?php

namespace App\Console\Commands;

use App\Models\Show;
use App\Models\ShowIngestionLog;
use App\Models\WhatnotChannel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Process\Process;

class SyncWhatnotShowIndex extends Command
{
    protected $signature = 'whatnot:sync-show-index
                            {--limit=50 : Maximum shows returned by the lightweight seller-hub scrape}
                            {--debug : Stream scraper diagnostics}';

    protected $description = 'Keep VortexOps show rows current from the authenticated Whatnot seller session without requiring analytics';

    public function handle(): int
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

        $limit = max(1, (int) $this->option('limit'));
        $rows = $this->scrape($limit, (bool) $this->option('debug'));

        if ($rows === null) {
            return self::FAILURE;
        }

        $created = 0;
        $updated = 0;
        $unchanged = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $liveId = $this->liveId($row);
            $title = trim((string) ($row['title'] ?? '')) ?: null;
            $showDate = $row['show_date'] ?? null;

            if (! $liveId || ! $title || ! $showDate) {
                $skipped++;
                continue;
            }

            $detailUrl = "https://www.whatnot.com/dashboard/live/{$liveId}";

            // UUID is the stable identity. The fallback catches rows imported before
            // whatnot_show_id started being populated, so the first index sync repairs
            // them instead of creating a duplicate.
            $show = Show::query()
                ->where('whatnot_show_id', $liveId)
                ->orWhere('detail_url', 'like', "%{$liveId}%")
                ->first();

            $payload = [
                'whatnot_channel_id' => $channel->id,
                'whatnot_show_id' => $liveId,
                'title' => $title,
                'show_date' => $showDate,
                'start_time' => $row['start_time'] ?? null,
                'detail_url' => $detailUrl,
                'last_synced_at' => now(),
                'import_source' => 'auto_whatnot',
                'raw_import_payload' => array_merge($row, [
                    '_channel_id' => $channel->id,
                    '_index_synced_at' => now()->toIso8601String(),
                ]),
            ];

            if ($show) {
                // Never let this lightweight index overwrite reviewed financial data;
                // it only owns show identity/scheduling metadata.
                $show->fill($payload);
                if ($show->isDirty()) {
                    $show->save();
                    $updated++;
                } else {
                    $unchanged++;
                }
            } else {
                $show = Show::create(array_merge($payload, [
                    'status' => 'draft',
                    'created_by' => auth()->id() ?? 1,
                ]));
                $show->detectStreamers();
                $created++;
            }

            ShowIngestionLog::create([
                'show_id' => $show->id,
                'source' => 'whatnot_show_index',
                'status' => 'success',
                'raw_payload' => array_merge($row, ['_channel_id' => $channel->id]),
            ]);
        }

        $this->info("Show index synced for {$channel->name}: {$created} created, {$updated} updated, {$unchanged} unchanged, {$skipped} skipped.");

        return self::SUCCESS;
    }

    private function scrape(int $limit, bool $debug): ?array
    {
        $env = [
            'WHATNOT_MODE' => 'shows',
            'WHATNOT_LIMIT' => (string) $limit,
            // Intentionally no WHATNOT_CHANNEL_NAME here. The saved browser session
            // is already in the correct seller context; channel switching is currently
            // the least reliable part of Whatnot and must not block the frequent poll.
            'WHATNOT_DEBUG' => $debug ? '1' : '0',
        ];

        foreach ([
            'WHATNOT_EMAIL' => config('vortex.whatnot.email'),
            'WHATNOT_PASSWORD' => config('vortex.whatnot.password'),
            'PLAYWRIGHT_BROWSERS_PATH' => config('vortex.whatnot.playwright_browsers_path') ?: '/opt/pw-browsers',
            'PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH' => config('vortex.whatnot.playwright_chromium_executable'),
            'WHATNOT_PROXY' => config('vortex.whatnot.proxy'),
        ] as $key => $value) {
            if ($value !== null && $value !== '') {
                $env[$key] = (string) $value;
            }
        }

        if (($headless = config('vortex.whatnot.headless')) !== null) {
            $env['WHATNOT_HEADLESS'] = $headless ? 'true' : 'false';
        }

        $node = config('vortex.whatnot.node_bin', 'node');
        $process = new Process([$node, base_path('scripts/whatnot-scraper.cjs')], base_path(), $env);
        $process->setTimeout(360);

        $lock = Cache::lock('whatnot:browser', 900);

        try {
            // Same lock key used by WhatnotScraper, so this frequent poll cannot
            // collide with order, shipment, ledger, or manual scraper runs.
            $lock->block(300);
            Cache::put('whatnot:browser:holder_pid', getmypid(), 900);

            $process->run(function (string $type, string $buffer) use ($debug): void {
                if ($debug && $type === Process::ERR) {
                    $this->output->write($buffer);
                }
            });
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException) {
            $this->warn('Show-index sync skipped: another Whatnot browser job is still running.');
            return [];
        } catch (\Throwable $e) {
            $this->error('Show-index scraper timed out or crashed: ' . $e->getMessage());
            return null;
        } finally {
            Cache::forget('whatnot:browser:holder_pid');
            try {
                $lock->release();
            } catch (\Throwable) {
                // Nothing else to do; the lock has its own TTL safety net.
            }
        }

        $stdout = trim($process->getOutput());
        $stderr = trim($process->getErrorOutput());

        if (! $process->isSuccessful()) {
            $this->error('Show-index scraper failed with exit code ' . $process->getExitCode() . '.');
            if ($stderr !== '') {
                $this->line($stderr);
            }
            return null;
        }

        $rows = json_decode($stdout, true);
        if (! is_array($rows)) {
            $this->error('Show-index scraper returned invalid JSON.');
            return null;
        }

        return $rows;
    }

    private function liveId(array $row): ?string
    {
        $candidate = $row['whatnot_live_id'] ?? $row['live_id'] ?? $row['id'] ?? null;
        if (is_string($candidate) && preg_match('/^[0-9a-f-]{36}$/i', $candidate)) {
            return $candidate;
        }

        $url = (string) ($row['detail_url'] ?? $row['url'] ?? '');
        if (preg_match('/([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})/i', $url, $m)) {
            return $m[1];
        }

        return null;
    }
}
