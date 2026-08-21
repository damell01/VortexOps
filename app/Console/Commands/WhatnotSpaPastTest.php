<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class WhatnotSpaPastTest extends Command
{
    protected $signature = 'whatnot:spa-past-test
                            {live-id=183498e1-fc7d-436b-a4a0-c042efba09b8 : Preferred past Whatnot livestream UUID}
                            {--debug : Save screenshots under /tmp}';

    protected $description = 'Test Past-tab show discovery, analytics, and exact shipment-table extraction; falls back to a real current-channel past show when needed';

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

        $this->info('Testing Past-tab flow. Preferred show: ' . $liveId);
        $this->line('Path: Seller Hub → Shows → Past → infinite scroll → exact Analytics / Shipments row links');
        $this->newLine();

        $process = new Process([
            config('vortex.whatnot.node_bin', 'node'),
            base_path('scripts/whatnot-spa-past-test-v6.cjs'),
            $liveId,
        ], base_path(), $env);
        $process->setTimeout(320);
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
            $this->line('Scroll passes: ' . $data['stages']['past']['scroll_passes']);
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

        $target = $data['target'] ?? null;
        if ($target) {
            $this->newLine();
            if (($data['target_source'] ?? null) === 'fallback-current-channel') {
                $this->warn('Preferred UUID was not on this seller/channel. Testing a real current-channel past show instead:');
            } else {
                $this->info('Preferred past-show row found:');
            }
            $this->line(json_encode($target, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->warn('No past show with both Analytics and Shipments links could be selected.');
        }

        if (! empty($data['stages']['analytics'])) {
            $this->newLine();
            $this->info('Analytics result:');
            $this->line(json_encode([
                'url' => $data['stages']['analytics']['url'] ?? null,
                'metrics' => $data['stages']['analytics']['metrics'] ?? [],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        if (! empty($data['stages']['shipments'])) {
            $ship = $data['stages']['shipments'];
            $this->newLine();
            $this->info('Shipments result:');
            $this->line('URL: ' . ($ship['url'] ?? ''));
            $this->line('Rows on current page: ' . ($ship['row_count'] ?? 0));
            if (! empty($ship['stats'])) {
                $this->line('Stats: ' . json_encode($ship['stats'], JSON_UNESCAPED_SLASHES));
            }
            if (! empty($ship['rows'])) {
                $table = [];
                foreach (array_slice($ship['rows'], 0, 10) as $row) {
                    $table[] = [
                        $row['recipient'] ?? '—',
                        $row['order_date'] ?? '—',
                        $row['items'] ?? '—',
                        $row['value'] ?? '—',
                        $row['weight'] ?? '—',
                        $row['dimensions'] ?? '—',
                        $row['status'] ?? '—',
                        $row['carrier'] ?? '—',
                        $row['tracking'] ?? '—',
                    ];
                }
                $this->table(['Recipient','Order Date','Items','Value','Weight','Dimensions','Status','Carrier','Tracking'], $table);
            }
        }

        $this->newLine();
        $this->info('GraphQL operations observed:');
        foreach ($data['operations'] ?? [] as $op) {
            $this->line('  ' . $op);
        }

        $analyticsOk = !empty($data['stages']['analytics'])
            && empty($data['stages']['analytics']['challenged'])
            && !empty($data['stages']['analytics']['metrics']);
        $shipmentsOk = !empty($data['stages']['shipments'])
            && empty($data['stages']['shipments']['challenged'])
            && (($data['stages']['shipments']['row_count'] ?? 0) > 0)
            && !empty($data['stages']['shipments']['rows']);

        $this->newLine();
        $this->line('Analytics: ' . ($analyticsOk ? '<info>PASS</info>' : '<error>FAIL</error>'));
        $this->line('Shipments: ' . ($shipmentsOk ? '<info>PASS</info>' : '<error>FAIL</error>'));

        return (!empty($rows) && $target && $analyticsOk && $shipmentsOk)
            ? self::SUCCESS
            : self::FAILURE;
    }
}
