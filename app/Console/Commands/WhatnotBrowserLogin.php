<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class WhatnotBrowserLogin extends Command
{
    protected $signature = 'whatnot:browser-login
                            {--port=9222 : Local Chrome DevTools port}
                            {--url=https://www.whatnot.com/login : Initial page to open}';

    protected $description = 'Open the scraper\'s persistent Chrome profile for a manual Whatnot login';

    public function handle(): int
    {
        $chrome = (string) (config('vortex.whatnot.chromium_executable_path')
            ?: env('PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH')
            ?: '/usr/bin/google-chrome-stable');

        if (! is_file($chrome) || ! is_executable($chrome)) {
            $this->error("Chrome executable is not available: {$chrome}");
            return self::FAILURE;
        }

        $profile = storage_path('whatnot-browser-profile');
        if (! is_dir($profile) && ! @mkdir($profile, 0775, true) && ! is_dir($profile)) {
            $this->error("Unable to create browser profile directory: {$profile}");
            return self::FAILURE;
        }

        $port = (int) $this->option('port');
        if ($port < 1024 || $port > 65535) {
            $this->error('The DevTools port must be between 1024 and 65535.');
            return self::FAILURE;
        }

        $url = trim((string) $this->option('url'));
        if (! str_starts_with($url, 'https://www.whatnot.com/')) {
            $this->error('The initial URL must be an https://www.whatnot.com/ URL.');
            return self::FAILURE;
        }

        $wrapper = base_path('scripts/with-xvfb.sh');
        if (! is_file($wrapper)) {
            $this->error("Xvfb wrapper is missing: {$wrapper}");
            return self::FAILURE;
        }

        // Stale Chrome singleton files can survive an unclean scraper exit and
        // prevent the exact same persistent profile from opening manually.
        foreach (['SingletonLock', 'SingletonCookie', 'SingletonSocket'] as $name) {
            $path = $profile . DIRECTORY_SEPARATOR . $name;
            if (is_link($path) || is_file($path)) {
                @unlink($path);
            }
        }

        $this->newLine();
        $this->info('Opening the exact persistent Chrome profile used by the Whatnot scraper.');
        $this->line("Profile: {$profile}");
        $this->line("Chrome:  {$chrome}");
        $this->line("URL:     {$url}");
        $this->newLine();
        $this->warn('This command does not type, submit, or automate your Whatnot credentials.');
        $this->line('Chrome DevTools is bound to 127.0.0.1 only so it is not exposed publicly.');
        $this->line("DevTools port: {$port}");
        $this->newLine();
        $this->line('Keep this command running while you complete the login manually.');
        $this->line('When you are signed in, close the Chrome window (or press Ctrl-C here).');
        $this->newLine();

        $process = new Process([
            'bash',
            $wrapper,
            $chrome,
            '--no-sandbox',
            '--disable-dev-shm-usage',
            '--remote-debugging-address=127.0.0.1',
            "--remote-debugging-port={$port}",
            "--user-data-dir={$profile}",
            '--no-first-run',
            '--no-default-browser-check',
            '--new-window',
            $url,
        ], base_path(), [
            'HOME' => storage_path('whatnot-browser-home'),
            'TZ' => 'America/Chicago',
        ]);

        // A manual login can reasonably take several minutes. No timeout here;
        // the operator closes Chrome or interrupts this command when finished.
        $process->setTimeout(null);
        $process->setIdleTimeout(null);

        $exit = $process->run(function (string $type, string $buffer): void {
            // Preserve Chrome/Xvfb diagnostics without trying to interpret them.
            $this->output->write($buffer);
        });

        // The profile itself is authoritative after a manual login. Remove the
        // bootstrap marker so a later explicit cookie import can still take
        // effect instead of being mistaken for already-loaded state.
        $marker = storage_path('whatnot-cookies.json.loaded-mtime');
        if (is_file($marker)) {
            @unlink($marker);
        }

        if ($exit !== 0 && $exit !== 130 && $exit !== 143) {
            $this->error("Chrome exited with code {$exit}.");
            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Manual browser session closed. Test the saved session with:');
        $this->line('  php artisan whatnot:login --test');

        return self::SUCCESS;
    }
}
