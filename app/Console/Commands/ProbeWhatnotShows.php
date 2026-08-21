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

        $env = [
            'WHATNOT_MODE' => 'shows',
            'WHATNOT_LIMIT' => (string) $limit,
            'WHATNOT_DEBUG' => $this->option('debug') ? '1' : '0',
        ];

        // Important: do NOT set WHATNOT_CHANNEL_NAME unless the caller explicitly
        // asks for one. The persisted browser session is already authenticated as
        // a seller, and role/channel switching is currently the operation hanging
        // on the VPS. For the probe we want to prove show retrieval independently
        // from account switching.
        if ($channel) {
            $env['WHATNOT_CHANNEL_NAME'] = (string) $channel->whatnot_username;
        }

        foreach ([
            'WHATNOT_EMAIL' => config('vortex.whatnot.email'),
            'WHATNOT_PASSWORD' => config('vortex.whatnot.password'),
            'PLAYWRIGHT_BROWSERS_PATH' => config('vortex.whatnot.playwright_browsers_path'),
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
        $script = base_path('scripts/whatnot-scraper.cjs');

        if ($channel) {
            $this->info("Probing {$channel->name} (@{$channel->whatnot_username}) for {$limit} show(s)…");
        } else {
            $this->info("Probing the seller channel already active in the saved Whatnot session for {$limit} show(s)…");
        }
        $this->line('Mode: shows (analytics page bypassed; channel switch bypassed unless --channel is supplied)');
        $this->newLine();

        $process = new Process([$node, $script], base_path(), $env);
        $process->setTimeout(300);
        $process->run(function (string $type, string $buffer): void {
            if ($type === Process::ERR) {
                $this->output->write($buffer);
            }
        });

        $stdout = trim($process->getOutput());
        $stderr = trim($process->getErrorOutput());

        if (! $process->isSuccessful()) {
            $this->error('Show-list probe failed with exit code ' . $process->getExitCode() . '.');
            if ($stderr !== '') {
                $this->line($stderr);
            }
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
