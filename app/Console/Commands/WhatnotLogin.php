<?php

namespace App\Console\Commands;

use App\Services\WhatnotScraper;
use Illuminate\Console\Command;

class WhatnotLogin extends Command
{
    protected $signature = 'whatnot:login
                            {--test     : Just test whether existing cookies are still valid}
                            {--save     : After testing credentials, dump the session cookies to storage/whatnot-cookies.json}
                            {--cookie-file= : Path to a cookie JSON file exported from Chrome to import}';

    protected $description = 'Set up and verify Whatnot authentication via session cookies';

    public function handle(WhatnotScraper $scraper): int
    {
        $cookiesFile = $scraper->cookiesFilePath();

        // ── Import cookies from a Chrome export ───────────────────────────────
        if ($importFile = $this->option('cookie-file')) {
            return $this->importCookieFile($scraper, $importFile, $cookiesFile);
        }

        // ── Test existing cookies ─────────────────────────────────────────────
        if ($this->option('test') || $scraper->hasCookieFile()) {
            return $this->testExistingCookies($scraper, $cookiesFile);
        }

        // ── No cookies yet — show the full setup guide ────────────────────────
        return $this->showSetupGuide($scraper, $cookiesFile);
    }

    private function importCookieFile(WhatnotScraper $scraper, string $importFile, string $cookiesFile): int
    {
        if (! file_exists($importFile)) {
            $this->error("File not found: {$importFile}");
            return self::FAILURE;
        }

        $raw = json_decode(file_get_contents($importFile), true);
        if (! is_array($raw)) {
            $this->error('File does not contain valid JSON. Export cookies as JSON from Cookie-Editor.');
            return self::FAILURE;
        }

        // Normalize various cookie export formats
        $sameSiteMap = ['no_restriction' => 'None', 'strict' => 'Strict', 'lax' => 'Lax', 'unspecified' => 'Lax'];
        $cookies = array_filter(array_map(function (array $c) use ($sameSiteMap) {
            if (! isset($c['name'], $c['value'])) return null;
            // Keep only whatnot.com cookies
            $domain = $c['domain'] ?? '.whatnot.com';
            if (! str_contains($domain, 'whatnot.com')) return null;
            return [
                'name'     => $c['name'],
                'value'    => $c['value'],
                'domain'   => $domain,
                'path'     => $c['path'] ?? '/',
                'expires'  => $c['expirationDate'] ?? $c['expires'] ?? -1,
                'httpOnly' => (bool) ($c['httpOnly'] ?? false),
                'secure'   => (bool) ($c['secure'] ?? true),
                'sameSite' => $sameSiteMap[strtolower($c['sameSite'] ?? '')] ?? 'Lax',
            ];
        }, $raw));

        if (empty($cookies)) {
            $this->error('No whatnot.com cookies found in the file. Make sure you exported from whatnot.com.');
            return self::FAILURE;
        }

        file_put_contents($cookiesFile, json_encode(array_values($cookies), JSON_PRETTY_PRINT));
        $this->info('Imported ' . count($cookies) . ' cookies → ' . $cookiesFile);

        return $this->testExistingCookies($scraper, $cookiesFile);
    }

    private function testExistingCookies(WhatnotScraper $scraper, string $cookiesFile): int
    {
        if (! $scraper->hasCookieFile()) {
            $this->warn('No cookie file found at: ' . $cookiesFile);
            $this->line('Run this command without --test to see setup instructions.');
            return self::FAILURE;
        }

        $this->line('Testing session cookies…');

        try {
            $result = $scraper->testCookieAuth();
            $this->info('✓ Cookies are valid! Seller Hub accessible at: ' . ($result['url'] ?? 'whatnot.com'));
            $this->line('');
            $this->line('You can now run:');
            $this->line('  <comment>php artisan whatnot:import --discover</comment>   — map all Whatnot API endpoints');
            $this->line('  <comment>php artisan whatnot:sync</comment>                — run incremental sync');
            return self::SUCCESS;
        } catch (\RuntimeException $e) {
            $this->error('✗ Cookie test failed: ' . $e->getMessage());
            $this->line('');
            $this->line('Your session has likely expired. Export fresh cookies from Chrome:');
            $this->line('  1. Log into <comment>whatnot.com</comment> in Chrome');
            $this->line('  2. Install <comment>Cookie-Editor</comment> extension');
            $this->line('  3. Click Cookie-Editor → Export → <comment>Export as JSON</comment>');
            $this->line('  4. Save the file, then run:');
            $this->line('     <comment>php artisan whatnot:login --cookie-file=/path/to/cookies.json</comment>');
            return self::FAILURE;
        }
    }

    private function showSetupGuide(WhatnotScraper $scraper, string $cookiesFile): int
    {
        $this->line('');
        $this->line('<fg=yellow;options=bold>Whatnot Authentication Setup</>');
        $this->line('<fg=gray>─────────────────────────────────────────────────</>');
        $this->line('');
        $this->line('Whatnot blocks automated logins. The fix: export session');
        $this->line('cookies from your real Chrome browser — just once.');
        $this->line('');
        $this->line('<options=bold>Step 1 — Install Cookie-Editor</>');
        $this->line('  Chrome Web Store → search "Cookie-Editor" by cgagnier');
        $this->line('  (or use any extension that exports cookies as JSON)');
        $this->line('');
        $this->line('<options=bold>Step 2 — Log into Whatnot</>');
        $this->line('  Go to <comment>https://www.whatnot.com/login</comment> and sign in normally.');
        $this->line('  Complete any 2FA prompts.');
        $this->line('');
        $this->line('<options=bold>Step 3 — Export cookies</>');
        $this->line('  While on whatnot.com, click the Cookie-Editor extension.');
        $this->line('  Click <comment>Export</comment> → <comment>Export as JSON</comment>.');
        $this->line('  Save the file anywhere — e.g. ~/Downloads/whatnot-cookies.json');
        $this->line('');
        $this->line('<options=bold>Step 4 — Import into VortexOps</>');
        $this->line('  <comment>php artisan whatnot:login --cookie-file=~/Downloads/whatnot-cookies.json</comment>');
        $this->line('');
        $this->line('<options=bold>Step 5 — Run discover mode to map all API endpoints</>');
        $this->line('  <comment>php artisan whatnot:import --discover</comment>');
        $this->line('');
        $this->line('<fg=gray>Cookies are saved to: ' . $cookiesFile . '</>');
        $this->line('<fg=gray>They typically stay valid for 30–90 days. Re-run this command</>');
        $this->line('<fg=gray>when sync starts failing to refresh them.</>');
        $this->line('');

        // Check if credentials are set — offer to try form login as a bonus step
        if (config('vortex.whatnot.email') && config('vortex.whatnot.password')) {
            $this->line('<fg=gray>WHATNOT_EMAIL/PASSWORD are set. Trying headless login first…</>');
            try {
                $count = $scraper->dumpSessionCookies();
                $this->info("✓ Headless login succeeded — {$count} cookies saved to {$cookiesFile}");
                $this->line('Run <comment>php artisan whatnot:login --test</comment> to verify, then');
                $this->line('<comment>php artisan whatnot:import --discover</comment> to map API endpoints.');
                return self::SUCCESS;
            } catch (\RuntimeException $e) {
                $this->warn('Headless login failed (expected if Kasada is blocking): ' . $e->getMessage());
                $this->line('Follow the manual cookie export steps above instead.');
            }
        }

        return self::SUCCESS;
    }
}
