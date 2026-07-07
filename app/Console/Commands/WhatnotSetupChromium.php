<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class WhatnotSetupChromium extends Command
{
    protected $signature = 'whatnot:setup-chromium';

    protected $description = 'Find the installed Chromium binary and write a marker file so the scraper always finds it';

    public function handle(): int
    {
        $browsersPath = env('PLAYWRIGHT_BROWSERS_PATH', '/opt/pw-browsers');

        // Ask Playwright itself where the binary is
        $result = shell_exec("node -e \"const {chromium}=require('playwright-core'); process.stdout.write(chromium.executablePath());\" 2>/dev/null");
        $path = $result ? trim($result) : null;

        if ($path && file_exists($path)) {
            $this->writeMarker($browsersPath, $path);
            return self::SUCCESS;
        }

        // Fall back: scan the browsers directory for any chromium-* revision
        $path = $this->scanForChromium($browsersPath);
        if ($path) {
            $this->writeMarker($browsersPath, $path);
            return self::SUCCESS;
        }

        $this->error("Chromium not found under {$browsersPath}.");
        $this->line("Run:  PLAYWRIGHT_BROWSERS_PATH={$browsersPath} npx playwright install chromium --with-deps");
        $this->line("Then: php artisan whatnot:setup-chromium");
        return self::FAILURE;
    }

    private function scanForChromium(string $base): ?string
    {
        if (! is_dir($base)) {
            return null;
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
        return null;
    }

    private function writeMarker(string $browsersPath, string $path): void
    {
        $marker = "{$browsersPath}/.chromium-path";
        file_put_contents($marker, $path);
        $this->info("Chromium found: {$path}");
        $this->info("Marker written: {$marker}");
        $this->line("The Whatnot scraper will now always use this path.");
    }
}
