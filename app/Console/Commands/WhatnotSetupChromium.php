<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class WhatnotSetupChromium extends Command
{
    protected $signature = 'whatnot:setup-chromium';

    protected $description = 'Find the installed Chromium binary and write a marker file so the scraper always finds it';

    public function handle(): int
    {
        // Ask Playwright itself where the binary is (works regardless of install location)
        $path = $this->askPlaywright();

        // Fall back: scan common install roots
        if (! $path) {
            $path = $this->scan();
        }

        if (! $path) {
            $this->error('Chromium not found. Run: npx playwright install chromium --with-deps');
            $this->line('Then re-run: php artisan whatnot:setup-chromium');
            return self::FAILURE;
        }

        // Write to storage/ (always writable) so the scraper can read it
        $marker = storage_path('chromium-path.txt');
        file_put_contents($marker, $path);
        $this->info("Chromium found: {$path}");
        $this->info("Marker written: {$marker}");
        $this->line('The Whatnot scraper will now always use this path.');
        return self::SUCCESS;
    }

    private function askPlaywright(): ?string
    {
        $result = shell_exec("node -e \"try{const {chromium}=require('playwright-core');process.stdout.write(chromium.executablePath());}catch(e){}\" 2>/dev/null");
        $p = $result ? trim($result) : null;
        return ($p && file_exists($p)) ? $p : null;
    }

    private function scan(): ?string
    {
        $roots = array_filter([
            env('PLAYWRIGHT_BROWSERS_PATH'),
            '/opt/pw-browsers',
            posix_getpwuid(posix_getuid())['dir'] . '/.cache/ms-playwright',
            '/root/.cache/ms-playwright',
            '/var/www/.cache/ms-playwright',
            '/home/www-data/.cache/ms-playwright',
        ]);

        foreach ($roots as $base) {
            if (! is_dir($base)) {
                continue;
            }
            $dirs = array_filter(scandir($base), fn ($d) => str_starts_with($d, 'chromium-'));
            rsort($dirs);
            foreach ($dirs as $dir) {
                foreach (['chrome-linux64/chrome', 'chrome-linux/chrome', 'chrome'] as $bin) {
                    $full = "{$base}/{$dir}/{$bin}";
                    if (file_exists($full)) {
                        return $full;
                    }
                }
            }
        }

        // Also try system chromium
        foreach (['/usr/bin/chromium', '/usr/bin/chromium-browser', '/usr/bin/google-chrome'] as $bin) {
            if (file_exists($bin)) {
                return $bin;
            }
        }

        return null;
    }
}
