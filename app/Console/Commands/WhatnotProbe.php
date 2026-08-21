<?php

namespace App\Console\Commands;

use App\Services\WhatnotScraper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Ask whether Whatnot answers plain HTTP from this machine.
 *
 * Every failure so far has been Cloudflare challenging the *browser*: the
 * session is valid, the first page loads, and the next navigation gets an
 * interactive "verifying you are human" spinner that never resolves. That
 * challenge is a browser-JS mechanism. A plain HTTPS request is judged on
 * different signals, and Whatnot is a Next.js app whose SSR HTML already
 * carries the page's data — so if this gets through, the scraper does not need
 * Chromium at all.
 *
 * This answers that in a few seconds instead of a few days of browser flags.
 */
class WhatnotProbe extends Command
{
    protected $signature = 'whatnot:probe
                            {--url=* : Extra URLs to probe alongside the defaults}
                            {--browser : Probe through the real browser instead of plain HTTP}
                            {--soft : Also ask for each page as a fetch from inside the one page that is served}
                            {--save= : Write the first successful response body here}';

    protected $description = 'Test whether Whatnot serves plain HTTP requests (no browser) with the saved session';

    private const DEFAULT_URLS = [
        'https://www.whatnot.com/seller',
        'https://www.whatnot.com/dashboard/lives',
        'https://www.whatnot.com/dashboard/lives?status=completed',
    ];

    /**
     * Pages worth knowing about, beyond the three the HTTP probe uses.
     *
     * The homepage is on the list because the scraper navigates to it to reach
     * the nav drawer, and it is the page that gets refused — so whether that is
     * the homepage specifically or the whole site is the question this answers.
     */
    private const BROWSER_URLS = [
        'https://www.whatnot.com/seller',
        'https://www.whatnot.com/',
        'https://www.whatnot.com/dashboard/lives',
        'https://www.whatnot.com/dashboard/analytics/overview',
        'https://www.whatnot.com/seller/shows',
    ];

    public function handle(WhatnotScraper $scraper): int
    {
        if ($this->option('browser')) {
            return $this->probeThroughBrowser($scraper);
        }

        $cookies = $this->cookies($scraper);

        if ($cookies === []) {
            $this->error('No session cookies found. Run `php artisan whatnot:login` first.');

            return self::FAILURE;
        }

        $this->line(count($cookies) . ' cookies loaded. Probing without a browser…');

        // Naming the egress matters as much as the result: "still blocked" means
        // opposite things depending on whether the tunnel was actually in use.
        $this->line($this->proxy()
            ? '<fg=gray>Egress: via proxy ' . $this->proxy() . '</>'
            : '<fg=gray>Egress: direct from this server (set WHATNOT_PROXY to route elsewhere)</>');

        $this->reportEgressIdentity();
        $this->newLine();

        $anyOk = false;

        foreach ([...self::DEFAULT_URLS, ...$this->option('url')] as $url) {
            $anyOk = $this->probe($url, $cookies) || $anyOk;
        }

        $this->newLine();

        if ($anyOk) {
            $this->info('Plain HTTP works. The scraper can read these pages without Chromium,');
            $this->line('which sidesteps the browser challenge entirely.');

            return self::SUCCESS;
        }

        // No Chromium was involved, so this does rule the browser environment
        // out — but not everything else. A plain client differs from a real one
        // in its TLS handshake as well as its address, and both are judged.
        $this->warn('Every URL was challenged or refused, with no browser involved at all.');
        $this->line('So the trigger is this request rather than the browser: either where it came from');
        $this->line('(a datacenter range, which Cloudflare judges harshly) or what it looks like on the');
        $this->line('wire. Route it through WHATNOT_PROXY to tell those apart — if a residential proxy');
        $this->line('gets through unchanged, it was the address.');

        return self::FAILURE;
    }

    /**
     * The same question, asked by the browser that actually does the work.
     *
     * The plain-HTTP probe and this one can disagree, and when they do the
     * disagreement is the finding: a page refused to curl but served to
     * Chromium means the address is acceptable and the earlier refusal was
     * about how the request looked, not where it came from.
     */
    private function probeThroughBrowser(WhatnotScraper $scraper): int
    {
        $urls = [...self::BROWSER_URLS, ...$this->option('url')];

        $this->line('Probing ' . count($urls) . ' page(s) through the real browser…');
        $this->line('<fg=gray>This uses the persistent profile, so it sees exactly what the scraper sees.</>');
        $this->newLine();

        try {
            $probe   = $scraper->probePathsInBrowser($urls, soft: (bool) $this->option('soft'));
            $results = $probe['navigations'];
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $served  = [];
        $refused = [];

        foreach ($results as $result) {
            $blocked = ($result['challenged'] ?? false) || (($result['status'] ?? 0) >= 400);
            $path    = str_replace('https://www.whatnot.com', '', $result['url']) ?: '/';

            $this->line(sprintf(
                '  %s  %-42s [%s]%s',
                $blocked ? '<fg=red>NO </>' : '<fg=green>YES</>',
                $path,
                $result['status'] ?? 'no response',
                ($result['challenged'] ?? false) ? ' Cloudflare challenge' : '',
            ));

            $blocked ? $refused[] = $path : $served[] = $path;
        }

        // What the app's own requests get, which is a different question from
        // what a navigation gets — and the one that decides whether a route
        // avoiding navigations is worth building.
        if (($probe['fetches'] ?? []) !== []) {
            $this->newLine();
            $this->line('Asked for as a fetch from inside /seller:');

            foreach ($probe['fetches'] as $fetch) {
                $blocked = ($fetch['challenged'] ?? false) || (($fetch['status'] ?? 0) >= 400);

                $this->line(sprintf(
                    '  %s  %-42s [%s] %s',
                    $blocked ? '<fg=red>NO </>' : '<fg=green>YES</>',
                    str_replace('https://www.whatnot.com', '', $fetch['url']) ?: '/',
                    $fetch['status'] ?? 'error',
                    ($fetch['challenged'] ?? false) ? 'Cloudflare challenge' : number_format($fetch['bytes'] ?? 0) . ' bytes',
                ));
            }

            $reachable = array_filter(
                $probe['fetches'],
                fn ($f) => ! ($f['challenged'] ?? false) && ($f['status'] ?? 0) < 400,
            );

            $this->newLine();
            $this->line($reachable !== []
                ? '<fg=green>The app\'s own requests get through where a navigation does not.</> A route that'
                    . ' loads /seller once and then fetches, rather than navigating, is worth building.'
                : 'The block applies to these requests too, so avoiding navigations would not help.');
        }

        $this->newLine();

        if ($refused === []) {
            $this->info('Every page was served. The block is not about which page is asked for.');

            return self::SUCCESS;
        }

        if ($served === []) {
            $this->warn('Every page was refused, so this is not path-scoped — the whole site is being');
            $this->line('withheld from this browser. Route it elsewhere with WHATNOT_PROXY to find out');
            $this->line('whether the address is what is being judged.');

            return self::FAILURE;
        }

        // The interesting case, and the one actually observed.
        $this->info('The rules here are path-scoped — some pages are served and some are not:');
        $this->line('  served:  ' . implode(', ', $served));
        $this->line('  refused: ' . implode(', ', $refused));
        $this->newLine();
        $this->line('That means the address and the browser are both acceptable, or nothing would be');
        $this->line('served at all. A route through the served pages is worth building; one that has');
        $this->line('to pass through a refused page is not, however the browser is configured.');

        return self::SUCCESS;
    }

    /** @param array<string, string> $cookies */
    private function probe(string $url, array $cookies): bool
    {
        try {
            $options = ['allow_redirects' => true];

            if ($proxy = $this->proxy()) {
                $options['proxy'] = $proxy;
            }

            $response = Http::withHeaders($this->browserHeaders())
                ->withCookies($cookies, 'www.whatnot.com')
                ->withOptions($options)
                ->timeout(30)
                ->get($url);
        } catch (\Throwable $e) {
            $this->line(sprintf('  <fg=red>ERR</>   %s — %s', $url, $e->getMessage()));

            return false;
        }

        $body    = $response->body();
        $status  = $response->status();
        $verdict = $this->classify($status, $body);

        $this->line(sprintf(
            '  %s  %s  <fg=gray>[%d, %s]</> %s',
            $verdict['ok'] ? '<fg=green>OK </>' : '<fg=red>NO </>',
            $url,
            $status,
            $this->humanSize(strlen($body)),
            $verdict['note'],
        ));

        if ($verdict['ok'] && $this->option('save')) {
            file_put_contents($this->option('save'), $body);
            $this->line('        <fg=gray>saved body → ' . $this->option('save') . '</>');
        }

        return $verdict['ok'];
    }

    /**
     * @return array{ok: bool, note: string}
     */
    private function classify(int $status, string $body): array
    {
        $text = strtolower($body);

        foreach (['verifying you are human', 'performing security verification', 'just a moment', 'cf-browser-verification'] as $marker) {
            if (str_contains($text, $marker)) {
                return ['ok' => false, 'note' => 'Cloudflare challenge'];
            }
        }

        if ($status === 403) return ['ok' => false, 'note' => 'refused (403)'];
        if ($status === 429) return ['ok' => false, 'note' => 'rate limited (429)'];
        if ($status >= 400)  return ['ok' => false, 'note' => "HTTP {$status}"];

        // Redirected to a login page means the cookies did not travel, which is a
        // different problem from being blocked and deserves a different message.
        if (preg_match('#/(login|signin)(\?|/|$)#i', $body) && str_contains($text, 'log in')) {
            return ['ok' => false, 'note' => 'looks like a signed-out page'];
        }

        // Next.js embeds the page's data in the SSR payload. Its presence is what
        // makes a browser-free scrape possible at all, so it is the thing worth
        // reporting — a 200 carrying no data would be a false win.
        $hasData = str_contains($body, '__NEXT_DATA__') || str_contains($body, 'self.__next_f');

        return $hasData
            ? ['ok' => true,  'note' => 'real page, Next.js data present']
            : ['ok' => false, 'note' => 'HTTP 200 but no Next.js payload'];
    }

    private function proxy(): ?string
    {
        return config('vortex.whatnot.proxy') ?: null;
    }

    /**
     * Report the IP the world actually sees, before probing anything.
     *
     * A misconfigured proxy fails silently in the worst possible way: requests
     * go out the server's own address, Whatnot blocks them exactly as before,
     * and the result reads as "the proxy didn't help" when the truth is the
     * proxy was never used. Cloudflare's trace endpoint answers both halves —
     * which IP, and whether WARP is carrying it.
     */
    private function reportEgressIdentity(): void
    {
        try {
            $options = ['allow_redirects' => false];

            if ($proxy = $this->proxy()) {
                $options['proxy'] = $proxy;
            }

            $body = Http::withOptions($options)
                ->timeout(15)
                ->get('https://www.cloudflare.com/cdn-cgi/trace')
                ->body();
        } catch (\Throwable $e) {
            $this->warn('  Could not confirm the egress IP: ' . $e->getMessage());

            if ($this->proxy()) {
                $this->line('  <fg=gray>If WARP is the proxy, check it is up: warp-cli status</>');
            }

            return;
        }

        $fields = [];
        foreach (explode("\n", $body) as $line) {
            [$k, $v] = array_pad(explode('=', trim($line), 2), 2, '');
            if ($k !== '') $fields[$k] = $v;
        }

        if (! isset($fields['ip'])) {
            $this->warn('  Could not read the egress IP (unexpected response).');

            return;
        }

        $warp = $fields['warp'] ?? 'off';

        $this->line(sprintf(
            '  <fg=gray>Seen by the world as %s%s</>',
            $fields['ip'],
            $warp === 'off' ? '' : "  (WARP: {$warp})",
        ));

        // The combination that wastes an afternoon: a proxy is configured, so
        // the run looks routed, but WARP is off and the address is the server's
        // own — meaning nothing moved and the result proves nothing.
        if ($this->proxy() && $warp === 'off') {
            $this->warn('  A proxy is set but WARP reports off — traffic may not be going through it.');
        }
    }

    /** @return array<string, string> */
    private function browserHeaders(): array
    {
        // Matching what the scraper's Chromium sends: a request whose headers
        // disagree with its User-Agent is itself a detection signal.
        return [
            'User-Agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36',
            'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
            'Accept-Language' => 'en-US,en;q=0.9',
            'sec-ch-ua'          => '"Chromium";v="128", "Google Chrome";v="128", "Not-A.Brand";v="99"',
            'sec-ch-ua-mobile'   => '?0',
            'sec-ch-ua-platform' => '"Windows"',
            'Sec-Fetch-Dest'  => 'document',
            'Sec-Fetch-Mode'  => 'navigate',
            'Sec-Fetch-Site'  => 'none',
            'Upgrade-Insecure-Requests' => '1',
        ];
    }

    /**
     * Prefer the cookies the browser was last actually using.
     *
     * whatnot-live-cookies.json is written from a live context and reflects any
     * session refresh Whatnot performed; the bootstrap file is whatever was
     * imported by hand, which can be older.
     *
     * @return array<string, string>
     */
    private function cookies(WhatnotScraper $scraper): array
    {
        $candidates = [
            storage_path('whatnot-live-cookies.json'),
            $scraper->cookiesFilePath(),
        ];

        foreach ($candidates as $path) {
            if (! is_readable($path)) continue;

            $raw = json_decode((string) file_get_contents($path), true);
            $raw = is_array($raw) ? ($raw['cookies'] ?? $raw) : null;

            if (! is_array($raw) || $raw === []) continue;

            $jar = [];
            foreach ($raw as $c) {
                if (is_array($c) && isset($c['name'], $c['value'])) {
                    $jar[$c['name']] = (string) $c['value'];
                }
            }

            if ($jar !== []) {
                $this->line('<fg=gray>Using ' . basename($path) . '</>');

                return $jar;
            }
        }

        return [];
    }

    private function humanSize(int $bytes): string
    {
        return $bytes >= 1048576
            ? round($bytes / 1048576, 1) . ' MB'
            : ($bytes >= 1024 ? round($bytes / 1024) . ' KB' : $bytes . ' B');
    }
}
