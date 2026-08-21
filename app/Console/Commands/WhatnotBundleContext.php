<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class WhatnotBundleContext extends Command
{
    protected $signature = 'whatnot:bundle-context
                            {chunk : Whatnot _next/static/chunks filename, e.g. 62319-....js}
                            {needle : Literal text to find in the bundle}
                            {--before=8000 : Characters to print before the match}
                            {--after=16000 : Characters to print after the match}
                            {--save= : Optional file path to save the extracted context}';

    protected $description = 'Fetch one Whatnot JS chunk through the authenticated Playwright session and print context around a GraphQL operation/fragment';

    public function handle(): int
    {
        $chunk = basename(trim((string) $this->argument('chunk')));
        $needle = (string) $this->argument('needle');
        $before = max(0, (int) $this->option('before'));
        $after = max(0, (int) $this->option('after'));

        if ($chunk === '' || ! str_ends_with($chunk, '.js')) {
            $this->error('chunk must be a .js filename.');
            return self::FAILURE;
        }

        if ($needle === '') {
            $this->error('needle cannot be empty.');
            return self::FAILURE;
        }

        $env = array_filter([
            'PLAYWRIGHT_BROWSERS_PATH' => config('vortex.whatnot.playwright_browsers_path') ?: '/opt/pw-browsers',
            'PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH' => config('vortex.whatnot.playwright_chromium_executable'),
            'WHATNOT_HEADLESS' => (($headless = config('vortex.whatnot.headless')) === null ? null : ($headless ? 'true' : 'false')),
            'WHATNOT_USER_DATA_DIR' => storage_path('whatnot-browser-profile'),
        ], fn ($value) => $value !== null && $value !== '');

        $node = config('vortex.whatnot.node_bin', 'node');
        $script = base_path('scripts/whatnot-bundle-context.cjs');

        $process = new Process([
            $node,
            $script,
            $chunk,
            $needle,
            (string) $before,
            (string) $after,
        ], base_path(), $env);
        $process->setTimeout(180);
        $process->run();

        if (! $process->isSuccessful()) {
            $this->error(trim($process->getErrorOutput()) ?: 'Bundle context helper failed.');
            return self::FAILURE;
        }

        $data = json_decode(trim($process->getOutput()), true);
        if (! is_array($data) || ! isset($data['context'])) {
            $this->error('Bundle context helper returned invalid JSON.');
            return self::FAILURE;
        }

        $this->info("Found '{$needle}' in {$chunk}");
        $this->line('Source: ' . ($data['url'] ?? 'unknown'));
        $this->line('Bundle bytes: ' . ($data['bundle_bytes'] ?? '?') . '; match offset: ' . ($data['match_offset'] ?? '?'));
        $this->newLine();
        $this->line($data['context']);

        if ($path = $this->option('save')) {
            file_put_contents($path, $data['context']);
            $this->newLine();
            $this->info("Saved context to {$path}");
        }

        return self::SUCCESS;
    }
}
