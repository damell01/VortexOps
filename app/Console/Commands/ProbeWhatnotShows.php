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
        $backend = strtolower((string) config('vortex.whatnot.browser_backend', 'local'));
        $cdpUrl = (string) config('vortex.whatnot.scrapling_cdp_url', 'http://127.0.0.1:9222');

        // Do not override the configured backend merely because a CDP endpoint
        // exists. Scrapling itself attaches to that same persistent Chrome via
        // WHATNOT_SCRAPLING_CDP_URL, so forcing `attached` here bypasses the
        // Scrapling runner entirely.
        $env = [
            'WHATNOT_MODE' => 'shows',
            'WHATNOT_LIMIT' => (string) $limit,
            'WHATNOT_DEBUG' => $this->option('debug') ? '1' : '0',
            'WHATNOT_BROWSER_BACKEND' => $backend,
            'WHATNOT_ATTACH_CDP_URL' => $cdpUrl,
            'WHATNOT_SCRAPLING_USE_CDP' => config('vortex.whatnot.scrapling_use_cdp', true) ? '1' : '0',
            'WHATNOT_SCRAPLING_CDP_URL' => $cdpUrl,
            'WHATNOT_SCRAPLING_SOLVE_CLOUDFLARE' => config('vortex.whatnot.scrapling_solve_cloudflare', false) ? 'true' : 'false',
            'WHATNOT_SCRAPLING_BLOCK_WEBRTC' => config('vortex.whatnot.scrapling_block_webrtc', false) ? 'true' : 'false',
            'WHATNOT_SCRAPLING_HIDE_CANVAS' => config('vortex.whatnot.scrapling_hide_canvas', false) ? 'true' : 'false',
            'WHATNOT_SCRAPLING_ALLOW_WEBGL' => config('vortex.whatnot.scrapling_allow_webgl', true) ? 'true' : 'false',
            'WHATNOT_SCRAPER_FALLBACK' => config('vortex.whatnot.scraper_fallback', false) ? '1' : '0',
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
        $this->line("Browser backend: {$backend}" . ($backend === 'scrapling' || $backend === 'scrapling-stealthy' ? " (CDP {$cdpUrl})" : ''));
        $this->newLine();

        // Always use the central runner. It owns backend selection. For the
        // production `scrapling` backend, the runner maps this to StealthySession
        // and attaches it to the existing persistent Chrome over CDP.
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

    private function resolveChannel(mixed $value): ?WhatnotChannel
    {
        return is_numeric($value)
            ? WhatnotChannel::find($value)
            : WhatnotChannel::where('name', $value)
                ->orWhere('whatnot_username', $value)
                ->first();
    }
}
