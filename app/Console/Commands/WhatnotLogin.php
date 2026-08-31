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

        // `--test` is deliberately read-only: callers asking only for a check
        // should never have credentials used behind their back.
        if ($this->option('test')) {
            return $this->testExistingCookies($scraper, $cookiesFile, allowRenewal: false);
        }

        // A stale cookie file used to make this command return FAILURE before
        // the credential-renewal path below was ever reached. Normal login now
        // tests the saved session first and, only when it is clearly expired,
        // renews it automatically when credentials are configured.
        if ($scraper->hasCookieFile()) {
            return $this->testExistingCookies($scraper, $cookiesFile, allowRenewal: true);
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

            // Cloudflare edge/challenge cookies are connection-specific state,
            // not Whatnot account authentication. Do not persist them as part
            // of the reusable application session bootstrap.
            if (in_array(strtolower($c['name']), [
                'cf_clearance', '__cf_bm', '__cfwaitingroom',
                'cf_chl_2', 'cf_chl_prog', 'cf_chl_rc_i', 'cf_chl_rc_ni', 'cf_chl_rc_m',
            ], true)) {
                return null;
            }
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
        $this->invalidateLoadedMarker($cookiesFile);
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

        return $this->testExistingCookies($scraper, $cookiesFile, allowRenewal: false);
    }

    private function testExistingCookies(WhatnotScraper $scraper, string $cookiesFile, bool $allowRenewal = false): int
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
            $message = $e->getMessage();
            $this->error('✗ Cookie test failed: ' . $message);
            $this->line('');

            // Explicit authentication evidence wins over incidental challenge
            // wording in diagnostics. A cookie-test that actually landed on
            // /login, or reported auth-required/expired cookies, is an expired
            // Whatnot session even if the diagnostic text also mentions what a
            // challenge would mean in other cases.
            if ($this->isExpiredSessionFailure($message)) {
                if ($allowRenewal && $this->hasConfiguredCredentials()) {
                    $this->warn('Saved Whatnot session is expired. Renewing it with configured credentials…');
                    return $this->renewFromCredentials($scraper, $cookiesFile);
                }

                $this->line('Your Whatnot session has expired. Export fresh cookies from Chrome:');
                $this->line('  1. Log into <comment>whatnot.com</comment> in Chrome');
                $this->line('  2. Install <comment>Cookie-Editor</comment> extension');
                $this->line('  3. Click Cookie-Editor → Export → <comment>Export as JSON</comment>');
                $this->line('  4. Save the file, then run:');
                $this->line('     <comment>php artisan whatnot:login --cookie-file=/path/to/cookies.json</comment>');
                return self::FAILURE;
            }

            // A Seller Hub challenge without explicit login/expiry evidence is
            // not proof that the Whatnot account session expired. Re-running
            // credential login here would only replace a potentially-valid
            // session and start another challenged navigation.
            if ($this->isBrowserChallenge($message)) {
                $this->warn('The saved Whatnot session could not be verified because Seller Hub was challenged.');
                $this->line('Authentication was not classified as expired, so credentials were not re-submitted.');
                return self::FAILURE;
            }

            $this->line('The saved session could not be verified. If it has expired, export fresh cookies from Chrome:');
            $this->line('  1. Log into <comment>whatnot.com</comment> in Chrome');
            $this->line('  2. Install <comment>Cookie-Editor</comment> extension');
            $this->line('  3. Click Cookie-Editor → Export → <comment>Export as JSON</comment>');
            $this->line('  4. Save the file, then run:');
            $this->line('     <comment>php artisan whatnot:login --cookie-file=/path/to/cookies.json</comment>');
            return self::FAILURE;
        }
    }

    private function renewFromCredentials(WhatnotScraper $scraper, string $cookiesFile): int
    {
        try {
            $count = $scraper->dumpSessionCookies();
            $this->invalidateLoadedMarker($cookiesFile);
            $this->info("✓ Credential renewal succeeded — {$count} cookies saved to {$cookiesFile}");
            $this->line('The renewed session will be force-loaded by the next scraper run.');
            return self::SUCCESS;
        } catch (\RuntimeException $e) {
            $this->error('✗ Credential renewal failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    private function invalidateLoadedMarker(string $cookiesFile): void
    {
        $marker = $cookiesFile . '.loaded-mtime';

        if (is_file($marker)) {
            @unlink($marker);
        }
    }

    private function hasConfiguredCredentials(): bool
    {
        return (bool) config('vortex.whatnot.email') && (bool) config('vortex.whatnot.password');
    }

    private function isBrowserChallenge(string $message): bool
    {
        return (bool) preg_match('/BOT_CHALLENGE|Cloudflare|challenge did not clear|verification page/i', $message);
    }

    private function isExpiredSessionFailure(string $message): bool
    {
        return (bool) preg_match(
            '/redirected to login|auth-required|cookies? (?:are )?(?:missing|expired|invalid)|session (?:has )?(?:lapsed|expired)|AUTH_REQUIRED/i',
            $message,
        );
    }

    private function showSetupGuide(WhatnotScraper $scraper, string $cookiesFile): int
    {
        $this->line('');
        $this->line('<fg=yellow;options=bold>Whatnot Authentication Setup</>');
        $this->line('<fg=gray>─────────────────────────────────────────────────</>');
        $this->line('');
        $this->line('Whatnot authentication can be bootstrapped from a saved browser session.');
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
        $this->line('<fg=gray>Re-run this command when sync starts failing to refresh them.</>');
        $this->line('');

        // Check if credentials are set — try form login as a convenience path.
        if ($this->hasConfiguredCredentials()) {
            $this->line('<fg=gray>WHATNOT_EMAIL/PASSWORD are set. Trying credential login first…</>');
            return $this->renewFromCredentials($scraper, $cookiesFile);
        }

        return self::SUCCESS;
    }
}
