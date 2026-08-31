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
    public const EXIT_SELECTOR_MISS  = 2;
    public const EXIT_AUTH_REQUIRED  = 3;
    public const EXIT_RATE_LIMITED   = 4;

    private string $scriptPath;
    private string $scraplingScriptPath;
    private string $nodeBin;
    private string $pythonBin;

    public function __construct()
    {
        $this->scriptPath          = base_path('scripts/whatnot-scraper.cjs');
        $this->scraplingScriptPath = base_path('scripts/whatnot-scrapling.py');
        $this->nodeBin             = config('vortex.whatnot.node_bin', 'node');
        $this->pythonBin           = config('vortex.whatnot.python_bin', 'python3');
    }

    public function testConnection(bool $debug = false): array
    {
        $env = $this->baseEnv($debug);
        $env['WHATNOT_MODE'] = 'test';
        $process = $this->makeProcess($env, timeout: 60);
        $this->withBrowserLock(fn () => $process->run());
        $stderr = trim($process->getErrorOutput());
        $stdout = trim($process->getOutput());
        if ($stderr) Log::channel('stack')->warning('WhatnotScraper testConnection stderr', ['output' => $stderr]);
        if (! $process->isSuccessful()) throw new \RuntimeException('Connection test failed: ' . ($stderr ?: "Scraper exited with code {$process->getExitCode()}"));
        $data = json_decode($stdout, true);
        if (json_last_error() !== JSON_ERROR_NONE || empty($data['connected'])) throw new \RuntimeException('Unexpected scraper response during test: ' . $stdout);
        return $data;
    }

    public function fetchShows(int $limit = 50, bool $debug = false, ?string $channelUsername = null, ?callable $onProgress = null, ?string $seedLiveId = null): array
    {
        $env = $this->baseEnv($debug);
        $env['WHATNOT_MODE'] = 'analytics';
        $env['WHATNOT_LIMIT'] = (string) $limit;
        if ($channelUsername) $env['WHATNOT_CHANNEL_NAME'] = $channelUsername;
        if ($seedLiveId) $env['WHATNOT_START_UUID'] = $seedLiveId;
        $timeoutSeconds = max(1200, (int) ceil($limit / 50) * 1200);
        $process = $this->makeProcess($env, timeout: $timeoutSeconds);
        $this->withBrowserLock(function () use ($process, $onProgress) {
            if ($onProgress) $this->streamProcess($process, $onProgress); else $process->run();
        }, waitSeconds: $timeoutSeconds);
        $stderr = trim($process->getErrorOutput());
        $stdout = trim($process->getOutput());
        if ($stderr) {
            Log::channel('stack')->warning('WhatnotScraper stderr', ['output' => $stderr, 'channel' => $channelUsername]);
            if ($debug) fwrite(STDERR, $stderr . "\n");
        }
        $this->throwForExitCode((int) $process->getExitCode(), $stderr, $process->getCommandLine());
        if (! $process->isSuccessful()) throw new \RuntimeException('Whatnot scraper failed: ' . ($stderr ?: "exit {$process->getExitCode()}"));
        if ($stdout === '') return [];
        $data = json_decode($stdout, true);
        if (json_last_error() !== JSON_ERROR_NONE) throw new \RuntimeException('Whatnot scraper returned invalid JSON: ' . json_last_error_msg());
        return is_array($data) ? $data : [];
    }

    public function fetchSellerShowUrls(bool $debug = false): array
    {
        $env = $this->baseEnv($debug); $env['WHATNOT_MODE'] = 'seller-shows';
        $process = $this->makeProcess($env, timeout: 120); $this->withBrowserLock(fn () => $process->run());
        $stderr = trim($process->getErrorOutput()); $stdout = trim($process->getOutput());
        if ($stderr) Log::channel('stack')->warning('WhatnotScraper seller-shows stderr', ['output' => $stderr]);
        $this->throwForExitCode((int) $process->getExitCode(), $stderr, $process->getCommandLine());
        if (! $process->isSuccessful()) throw new \RuntimeException('Seller-shows scraper failed: ' . ($stderr ?: "exit {$process->getExitCode()}"));
        $data = json_decode($stdout, true); return is_array($data) ? $data : [];
    }

    public function importDetailUrls(bool $debug = false): array
    {
        $rows = $this->fetchSellerShowUrls($debug); $updated = 0; $unmatched = 0;
        foreach ($rows as $row) {
            if (empty($row['detail_url'])) continue;
            $title = isset($row['title']) ? trim($row['title']) : null; $date = $row['show_date'] ?? null;
            $query = Show::query()->whereNull('detail_url');
            if ($title && $date) $query->where(function ($q) use ($title, $date) { $q->where(fn ($q2) => $q2->where('title', $title)->whereDate('show_date', $date))->orWhere(fn ($q2) => $q2->whereDate('show_date', $date)); });
            elseif ($date) $query->whereDate('show_date', $date); elseif ($title) $query->where('title', $title); else { $unmatched++; continue; }
            $show = $query->orderByRaw($title ? 'title = ? DESC' : 'id DESC', $title ? [$title] : [])->first();
            if ($show) { $show->update(['detail_url' => $row['detail_url']]); $updated++; } else $unmatched++;
        }
        return compact('updated', 'unmatched');
    }

    public function fetchShowOrders(string $showUrl, bool $debug = false): array
    {
        $env = $this->baseEnv($debug); $env['WHATNOT_MODE'] = 'show-orders'; $env['WHATNOT_SHOW_URL'] = $showUrl;
        $process = $this->makeProcess($env); $this->withBrowserLock(fn () => $process->run());
        $stderr = trim($process->getErrorOutput()); $stdout = trim($process->getOutput());
        if ($stderr) Log::channel('stack')->warning('WhatnotScraper show-orders stderr', ['output' => $stderr]);
        $this->throwForExitCode((int) $process->getExitCode(), $stderr, $process->getCommandLine());
        if (! $process->isSuccessful()) throw new \RuntimeException('Order scraper failed: ' . ($stderr ?: "exit {$process->getExitCode()}"));
        $data = json_decode($stdout, true); return is_array($data) ? $data : [];
    }

    /* Existing persistence/import methods below are intentionally unchanged. */
