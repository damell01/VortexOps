<?php

namespace App\Console\Commands;

use App\Services\WhatnotScraper;
use Illuminate\Console\Command;

class WhatnotLogin extends Command
{
    protected $signature = 'whatnot:login
                            {--test     : Just test whether existing cookies are still valid}
                            {--save     : After testing credentials, dump the session cookies to storage/whatnot-cookies.json}
                            {--paste    : Paste the cookie JSON in directly instead of saving it to a file first}
                            {--no-verify : Save the cookies without testing them against Seller Hub}
                            {--cookie-file= : Path to a cookie JSON file exported from Chrome to import; "-" reads stdin}';

    protected $description = 'Set up and verify Whatnot authentication via session cookies';

    public function handle(WhatnotScraper $scraper): int
    {
        $cookiesFile = $scraper->cookiesFilePath();

        // ── Import cookies pasted straight into the terminal ──────────────────
        if ($this->option('paste')) {
            return $this->importCookieJson($scraper, $this->readStdin(), $cookiesFile);
        }

        // ── Import cookies from a Chrome export ───────────────────────────────
        if ($importFile = $this->option('cookie-file')) {
            return $importFile === '-'
                ? $this->importCookieJson($scraper, $this->readStdin(), $cookiesFile)
                : $this->importCookieFile($scraper, $importFile, $cookiesFile);
        }

        // ── Test existing cookies ─────────────────────────────────────────────
        if ($this->option('test') || $scraper->hasCookieFile()) {
            return $this->testExistingCookies($scraper, $cookiesFile);
        }

        // ── No cookies yet — show the full setup guide ────────────────────────
        return $this->showSetupGuide($scraper, $cookiesFile);
    }

    /**
     * Read the whole of stdin, not one line of it.
     *
     * Cookie-Editor exports pretty-printed JSON spanning hundreds of lines, so
     * the obvious ask() here truncated the paste to its first line — "[" — and
     * reported invalid JSON. Reading to EOF takes the paste whole, and equally
     * takes a pipe or a heredoc, which is what --cookie-file=- is for.
     */
    private function readStdin(): string
    {
        if (defined('STDIN') && @stream_isatty(STDIN)) {
            $this->line('Paste the cookie JSON, then press <options=bold>Ctrl-D</> on a new line:');
        }

        return (string) file_get_contents('php://stdin');
    }

    private function importCookieFile(WhatnotScraper $scraper, string $importFile, string $cookiesFile): int
    {
        // The setup guide below tells people to type --cookie-file=~/Downloads/…,
        // and bash does not expand a tilde that follows an "=". Expanding it here
        // means the documented command actually works.
        if (str_starts_with($importFile, '~/')) {
            $importFile = rtrim((string) (getenv('HOME') ?: ''), '/') . substr($importFile, 1);
        }

        if (! file_exists($importFile)) {
            $this->error("File not found: {$importFile}");
            return self::FAILURE;
        }

        return $this->importCookieJson($scraper, (string) file_get_contents($importFile), $cookiesFile);
    }

    private function importCookieJson(WhatnotScraper $scraper, string $json, string $cookiesFile): int
    {
        $raw = json_decode(trim($json), true);

        // Cookie-Editor emits a bare array; Playwright's storageState wraps one
        // in an object alongside localStorage. Accepting both means not having
        // to care which tool the session came out of.
        if (is_array($raw) && isset($raw['cookies']) && is_array($raw['cookies'])) {
            $raw = $raw['cookies'];
        }

        if (! is_array($raw) || $raw === []) {
            $this->error('That is not a valid cookie JSON export. Export cookies as JSON from Cookie-Editor.');
            return self::FAILURE;
        }

        // Normalize various cookie export formats
        $sameSiteMap = ['no_restriction' => 'None', 'strict' => 'Strict', 'lax' => 'Lax', 'unspecified' => 'Lax'];
        $cookies = array_filter(array_map(function ($c) use ($sameSiteMap) {
            if (! is_array($c) || ! isset($c['name'], $c['value'])) return null;
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

        // A session cookie is the whole point of the export. Saying so here beats
        // a cookie test that fails for reasons nobody can read.
        $looksSignedIn = (bool) array_filter(
            $cookies,
            fn ($c) => (bool) preg_match('/session|token|auth/i', $c['name']),
        );

        if (! $looksSignedIn) {
            $this->warn('None of these look like a session cookie — document.cookie cannot see HttpOnly ones.');
            $this->line('If the check below fails, export with the Cookie-Editor extension instead.');
        }

        if ($this->option('no-verify')) {
            return self::SUCCESS;
        }

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
        $this->line('<fg=gray>  No extension handy? On the Seller Hub tab, open DevTools and run:</>');
        $this->line('  <fg=cyan>copy(document.cookie.split("; ").map(c => {</>');
        $this->line('  <fg=cyan>  const [name, ...v] = c.split("=");</>');
        $this->line('  <fg=cyan>  return { name, value: v.join("="), domain: ".whatnot.com", path: "/" };</>');
        $this->line('  <fg=cyan>}))</>');
        $this->line('<fg=gray>  then run</> <comment>php artisan whatnot:login --paste</comment> <fg=gray>and paste it in.</>');
        $this->line('<fg=gray>  This route cannot see HttpOnly cookies, so prefer the extension if it fails.</>');
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
