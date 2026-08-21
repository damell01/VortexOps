<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class WhatnotSpaPastTest extends Command
{
    protected $signature = 'whatnot:spa-past-test
                            {live-id=183498e1-fc7d-436b-a4a0-c042efba09b8 : Known past Whatnot livestream UUID}
                            {--debug : Save screenshots under /tmp}';

    protected $description = 'Test Past-tab show discovery, analytics, and shipments using infinite-scroll pagination and exact row links';

    public function handle(): int
    {
        $liveId = trim((string) $this->argument('live-id'));
        $env = [];

        foreach ([
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
        $env['WHATNOT_DEBUG'] = $this->option('debug') ? '1' : '0';

        $this->info('Testing Past-tab flow for show: ' . $liveId);
        $this->line('Path: authenticated Seller Hub → /dashboard/lives → Past → infinite-scroll pagination → exact Analytics / Shipments links');
        $this->newLine();

        $process = new Process([
            config('vortex.whatnot.node_bin', 'node'),
            base_path('scripts/whatnot-spa-past-test-v4.cjs'),
            $liveId,
        ], base_path(), $env);
        $process->setTimeout(240);
        $process->run(function (string $type, string $buffer): void {
            if ($type === Process::ERR) {
                $this->output->write($buffer);
            }
        });

        if (! $process->isSuccessful()) {
            $this->error('SPA test failed with exit code ' . $process->getExitCode());
            $this->line(trim($process->getErrorOutput()));
            return self::FAILURE;
        }

        $data = json_decode(trim($process->getOutput()), true);
        if (! is_array($data)) {
            $this->error('SPA test returned invalid JSON.');
            $this->line($process->getOutput());
            return self::FAILURE;
        }

        foreach (['home','shows','past','analytics','shipments'] as $stage) {
            if (! isset($data['stages'][$stage])) continue;
            $row = $data['stages'][$stage];
            $this->line(sprintf(
                '%-10s  %-5s  %s',
                strtoupper($stage),
                !empty($row['challenged']) ? 'BLOCK' : 'OK',
                $row['url'] ?? ''
            ));
        }

        $this->newLine();
        $rows = $data['past_rows'] ?? [];
        $this->info('Past rows loaded: ' . count($rows));
        if (isset($data['stages']['past']['scroll_passes'])) {
            $this->line('Scroll passes: ' . ($data['stages']['past']['scroll_passes'] ?? 'until stable'));
        }

        if ($rows) {
            $table = [];
            foreach (array_slice($rows, 0, 15) as $row) {
                $table[] = [
                    $row['live_id'] ?? '—',
                    $row['title'] ?? '—',
                    !empty($row['shipments_url']) ? 'yes' : 'no',
                    !empty($row['analytics_url']) ? 'yes' : 'no',
                ];
            }
            $this->table(['Live ID', 'Title', 'Shipments', 'Analytics'], $table);
        }

        if (! empty($data['target'])) {
            $this->newLine();
            $this->info('Target past-show row found:');
            $this->line(json_encode($data['target'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->warn('Target past show was not found before the Past list stopped growing or the safety cap was reached.');
        }

        if (! empty($data['stages']['analytics'])) {
            $this->newLine();
            $this->info('Analytics result:');
            $this->line(json_encode($data['stages']['analytics'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        if (! empty($data['stages']['shipments'])) {
            $this->newLine();
            $this->info('Shipments result:');
            $this->line(json_encode($data['stages']['shipments'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        $this->newLine();
        $this->info('GraphQL operations observed:');
        foreach ($data['operations'] ?? [] as $op) {
            $this->line('  ' . $op);
        }

        $ok = !empty($rows)
            && !empty($data['target'])
            && empty($data['stages']['past']['challenged']);

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
