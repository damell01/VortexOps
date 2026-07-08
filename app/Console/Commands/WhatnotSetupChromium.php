<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class WhatnotSetupChromium extends Command
{
    protected $signature = 'whatnot:setup-chromium';

    protected $description = 'Find the installed Chromium binary and write a marker file so the scraper always finds it';

    public function handle(): int
    {
        // 1. Try shared / www-data-accessible locations first
        $path = $this->scanShared();

        // 2. Ask Playwright's Node API (may return a root-only path)
        if (! $path) {
            $path = $this->askPlaywright();
        }

        // 3. Scan all roots including /root/.cache (last resort)
        if (! $path) {
            $path = $this->scan();
        }

        if (! $path) {
            $this->error('Chromium not found.');
            $this->line('');
            $this->line('Install Chromium to a shared location and re-run this command:');
            $this->line('  PLAYWRIGHT_BROWSERS_PATH=/opt/pw-browsers npx playwright install chromium --with-deps');
            $this->line('  php artisan whatnot:setup-chromium');
            return self::FAILURE;
        }

        // Warn if the binary lives under /root — the web process (www-data) can't access it.
        if (str_starts_with($path, '/root/')) {
            $this->warn("Chromium was found at {$path}");
            $this->warn('This path is only accessible as root. The web server (www-data) cannot use it.');
            $this->line('');
            $this->line('Fix: reinstall Chromium to a shared location:');
            $this->line('  PLAYWRIGHT_BROWSERS_PATH=/opt/pw-browsers npx playwright install chromium --with-deps');
            $this->line('  php artisan whatnot:setup-chromium');
            $this->line('');

            if (! $this->confirm('Write this path anyway? (scraper will fail until you reinstall)', false)) {
                return self::FAILURE;
            }
        }

        $marker = storage_path('chromium-path.txt');
        file_put_contents($marker, $path);
        $this->info("Chromium found: {$path}");
        $this->info("Marker written: {$marker}");
        $this->line('The Whatnot scraper will now always use this path.');
        return self::SUCCESS;
    }

    /**
     * Scan only locations that are world-readable (accessible by www-data).
     */
    private function scanShared(): ?string
    {
        $sharedRoots = array_filter([
            env('PLAYWRIGHT_BROWSERS_PATH'),
            '/opt/pw-browsers',
            '/usr/local/lib/playwright',
            '/var/www/.cache/ms-playwright',
            '/home/www-data/.cache/ms-playwright',
        ]);

        foreach ($sharedRoots as $base) {
            $found = $this->scanRoot($base);
            if ($found) {
                return $found;
            }
        }

        foreach (['/usr/bin/chromium', '/usr/bin/chromium-browser', '/usr/bin/google-chrome'] as $bin) {
            if (file_exists($bin)) {
                return $bin;
            }
        }

        return null;
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
            posix_getpwuid(posix_getuid())['dir'] . '/.cache/ms-playwright',
            '/root/.cache/ms-playwright',
        ]);

        foreach ($roots as $base) {
            $found = $this->scanRoot($base);
            if ($found) {
                return $found;
            }
        }

        return null;
    }

    private function scanRoot(string $base): ?string
    {
        if (! is_dir($base)) {
            return null;
        }

        // Some Playwright installs place the binary directly under the base dir
        // (e.g. PLAYWRIGHT_BROWSERS_PATH=/opt/pw-browsers → base/chrome-linux/chrome)
        foreach (['chrome-linux64/chrome', 'chrome-linux/chrome', 'chrome'] as $bin) {
            $full = "{$base}/{$bin}";
            if (file_exists($full)) {
                return $full;
            }
        }

        $dirs = array_filter(scandir($base) ?: [], fn ($d) => str_starts_with($d, 'chromium-'));
        rsort($dirs);

        foreach ($dirs as $dir) {
            foreach (['chrome-linux64/chrome', 'chrome-linux/chrome', 'chrome'] as $bin) {
                $full = "{$base}/{$dir}/{$bin}";
                if (file_exists($full)) {
                    return $full;
                }
            }
        }

        return null;
    }
}
