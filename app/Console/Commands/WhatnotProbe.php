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
                            {--save= : Write the first successful response body here}';

    protected $description = 'Test whether Whatnot serves plain HTTP requests (no browser) with the saved session';

    private const DEFAULT_URLS = [
        'https://www.whatnot.com/seller',
        'https://www.whatnot.com/dashboard/lives',
        'https://www.whatnot.com/dashboard/lives?status=completed',
    ];

    public function handle(WhatnotScraper $scraper): int
    {
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

        $this->warn('Every URL was challenged or refused. Cloudflare is judging this machine, not the browser —');
        $this->line('which points at the datacenter IP rather than anything fixable in the scraper.');

        return self::FAILURE;
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
