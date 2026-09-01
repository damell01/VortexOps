<?php

namespace App\Console\Commands;

use App\Models\WhatnotChannel;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class ProbeWhatnotShows extends Command
{
    protected $signature = 'whatnot:probe-shows
                            {--channel= : Channel name or ID; omit to use the seller channel already active in the browser session}
                            {--limit=10 : Maximum shows to return}
                            {--debug : Enable scraper debug output}';

    protected $description = 'Probe Whatnot shows mode without touching analytics; defaults to the currently active seller session';

    public function handle(): int
    {
        $channel = $this->option('channel') ? $this->resolveChannel($this->option('channel')) : null;

        if ($this->option('channel') && ! $channel) {
            $this->error('Channel not found: ' . $this->option('channel'));
            return self::FAILURE;
        }

        $limit = max(1, (int) $this->option('limit'));
        $configuredBackend = strtolower((string) config('vortex.whatnot.browser_backend', 'local'));

        // If a human-owned Chromium is already exposing the local CDP endpoint,
        // always borrow it for this diagnostic probe. This command must never
        // launch another Chromium against the same persistent profile because
        // the launcher's stale-lock recovery can terminate the manual browser.
        $cdpUrl = 'http://127.0.0.1:9222';
        $backend = $this->localCdpAvailable() ? 'attached' : $configuredBackend;

        $env = [
            'WHATNOT_MODE' => 'shows',
            'WHATNOT_LIMIT' => (string) $limit,
            'WHATNOT_DEBUG' => $this->option('debug') ? '1' : '0',
            'WHATNOT_BROWSER_BACKEND' => $backend,
            'WHATNOT_ATTACH_CDP_URL' => $cdpUrl,
            'STEEL_BASE_URL' => (string) config('vortex.whatnot.steel_base_url', 'http://127.0.0.1:3000'),
        ];

        // Important: do NOT set WHATNOT_CHANNEL_NAME unless the caller explicitly
        // asks for one. The persisted browser session is already authenticated as
        // a seller, and role/channel switching is intentionally tested only when
        // --channel is supplied.
        if ($channel) {
            $env['WHATNOT_CHANNEL_NAME'] = (string) $channel->whatnot_username;
        }

        foreach ([
            'WHATNOT_EMAIL' => config('vortex.whatnot.email'),
            'WHATNOT_PASSWORD' => config('vortex.whatnot.password'),
            'PLAYWRIGHT_BROWSERS_PATH' => config('vortex.whatnot.playwright_browsers_path'),
            'PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH' => config('vortex.whatnot.playwright_chromium_executable'),
        ] as $key => $value) {
            if ($value !== null && $value !== '') {
                $env[$key] = (string) $value;
            }
        }

        if (($headless = config('vortex.whatnot.headless')) !== null) {
            $env['WHATNOT_HEADLESS'] = $headless ? 'true' : 'false';
        }

        $node = config('vortex.whatnot.node_bin', 'node');
        $script = base_path('scripts/whatnot-runner.cjs');

        if ($channel) {
            $this->info("Probing {$channel->name} (@{$channel->whatnot_username}) for {$limit} show(s)…");
        } else {
            $this->info("Probing the seller channel already active in the saved Whatnot session for {$limit} show(s)…");
        }
        $this->line('Mode: shows (analytics page bypassed; channel switch bypassed unless --channel is supplied)');

        if ($backend === 'attached') {
            $this->line('Attached mode: existing Chromium detected on 127.0.0.1:9222; this probe will not launch or stop Chromium.');
        }

        $this->newLine();

        // Always use the central runner. It owns backend selection and the
        // attached-browser path. Do not call whatnot-scraper.cjs directly here.
        $process = new Process([$node, $script], base_path(), $env);
        $process->setTimeout(300);
        $process->run(function (string $type, string $buffer): void {
            if ($type === Process::ERR) {
                $this->output->write($buffer);
            }
        });

        $stdout = trim($process->getOutput());

        if (! $process->isSuccessful()) {
            $this->error('Show-list probe failed with exit code ' . $process->getExitCode() . '.');
            return self::FAILURE;
        }

        $rows = json_decode($stdout, true);
        if (! is_array($rows)) {
            $this->error('Scraper returned invalid JSON.');
            $this->line($stdout ?: '(empty stdout)');
            return self::FAILURE;
        }

        if ($rows === []) {
            $this->warn('Scraper completed but returned zero shows.');
            return self::SUCCESS;
        }

        $table = [];
        foreach (array_slice($rows, 0, $limit) as $i => $row) {
            $table[] = [
                $i + 1,
                $row['title'] ?? '—',
                $row['show_date'] ?? $row['date'] ?? '—',
                $row['whatnot_live_id'] ?? $row['live_id'] ?? '—',
                $row['detail_url'] ?? $row['url'] ?? '—',
            ];
        }

        $this->newLine();
        $this->info('Shows returned: ' . count($rows));
        $this->table(['#', 'Title', 'Date', 'Live ID', 'Detail URL'], $table);

        $this->newLine();
        $this->line('Raw first row:');
        $this->line(json_encode($rows[0], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }

    private function localCdpAvailable(): bool
    {
        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client('tcp://127.0.0.1:9222', $errno, $errstr, 0.25);

        if (! is_resource($socket)) {
            return false;
        }

        fclose($socket);
        return true;
    }

    private function resolveChannel(mixed $value): ?WhatnotChannel
    {
        return is_numeric($value)
            ? WhatnotChannel::find($value)
            : WhatnotChannel::where('name', $value)
                ->orWhere('whatnot_username', $value)
                ->first();
    }
}
