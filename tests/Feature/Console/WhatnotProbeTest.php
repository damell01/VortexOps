<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * whatnot:probe answers one question — does Whatnot serve this machine over
 * plain HTTP, without a browser — so its verdict has to be trustworthy. A
 * false "OK" would send us building a browser-free pipeline on a page that
 * carries no data.
 */
class WhatnotProbeTest extends TestCase
{
    private string $cookieFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cookieFile = storage_path('whatnot-live-cookies.json');

        if (file_exists($this->cookieFile)) {
            rename($this->cookieFile, $this->cookieFile . '.test-backup');
        }

        file_put_contents($this->cookieFile, json_encode([
            ['name' => 'sessionid', 'value' => 'abc', 'domain' => '.whatnot.com'],
        ]));
    }

    protected function tearDown(): void
    {
        @unlink($this->cookieFile);

        if (file_exists($this->cookieFile . '.test-backup')) {
            rename($this->cookieFile . '.test-backup', $this->cookieFile);
        }

        parent::tearDown();
    }

    public function test_a_real_next_js_page_is_reported_as_working(): void
    {
        Http::fake(['*' => Http::response('<html><body><script id="__NEXT_DATA__">{}</script></body></html>')]);

        $this->artisan('whatnot:probe')
            ->expectsOutputToContain('Next.js data present')
            ->assertExitCode(0);
    }

    public function test_a_cloudflare_challenge_is_not_mistaken_for_success(): void
    {
        // 200 OK with challenge HTML is exactly how Cloudflare answers, so
        // status alone would call this a win.
        Http::fake(['*' => Http::response(
            '<html><body>Verifying you are human. This may take a few seconds.</body></html>', 200,
        )]);

        $this->artisan('whatnot:probe')
            ->expectsOutputToContain('Cloudflare challenge')
            ->assertExitCode(1);
    }

    public function test_a_page_without_the_next_payload_is_not_counted_as_success(): void
    {
        // A 200 carrying no data is useless for scraping even though nothing
        // blocked it — reporting it as OK would be the expensive kind of wrong.
        Http::fake(['*' => Http::response('<html><body>hello</body></html>')]);

        $this->artisan('whatnot:probe')
            ->expectsOutputToContain('no Next.js payload')
            ->assertExitCode(1);
    }

    public function test_it_names_the_egress_so_a_result_can_be_interpreted(): void
    {
        // "Still blocked" means opposite things depending on whether the proxy
        // was actually carrying the request, so the run has to say which.
        Http::fake(['*' => Http::response('nope', 403)]);

        config(['vortex.whatnot.proxy' => 'socks5://127.0.0.1:40000']);
        $this->artisan('whatnot:probe')->expectsOutputToContain('via proxy socks5://127.0.0.1:40000')->assertExitCode(1);

        config(['vortex.whatnot.proxy' => null]);
        $this->artisan('whatnot:probe')->expectsOutputToContain('direct from this server')->assertExitCode(1);
    }

    public function test_it_reports_the_egress_ip_and_warp_state(): void
    {
        Http::fake([
            'cloudflare.com/cdn-cgi/trace' => Http::response("fl=1a\nip=104.28.1.9\nwarp=on\n"),
            '*' => Http::response('<html>__NEXT_DATA__</html>'),
        ]);

        config(['vortex.whatnot.proxy' => 'socks5://127.0.0.1:40000']);

        // One assertion covering both halves — the runner matches a single
        // expected fragment per run, and the IP and WARP state share a line.
        $this->artisan('whatnot:probe')
            ->expectsOutputToContain('Seen by the world as 104.28.1.9  (WARP: on)')
            ->assertExitCode(0);
    }

    public function test_it_warns_when_a_proxy_is_set_but_warp_is_off(): void
    {
        // The silent-failure case: a proxy is configured so the run looks
        // routed, but traffic is still leaving the server's own address — the
        // result would prove nothing and read as "the proxy didn't help".
        Http::fake([
            'cloudflare.com/cdn-cgi/trace' => Http::response("ip=203.0.113.7\nwarp=off\n"),
            '*' => Http::response('blocked', 403),
        ]);

        config(['vortex.whatnot.proxy' => 'socks5://127.0.0.1:40000']);

        $this->artisan('whatnot:probe')
            ->expectsOutputToContain('WARP reports off')
            ->assertExitCode(1);
    }

    public function test_an_unreachable_proxy_is_reported_rather_than_crashing(): void
    {
        Http::fake([
            'cloudflare.com/cdn-cgi/trace' => fn () => throw new \RuntimeException('Connection refused'),
            '*' => Http::response('blocked', 403),
        ]);

        config(['vortex.whatnot.proxy' => 'socks5://127.0.0.1:40000']);

        $this->artisan('whatnot:probe')
            ->expectsOutputToContain('warp-cli status')
            ->assertExitCode(1);
    }

    public function test_a_refusal_is_reported_as_such(): void
    {
        Http::fake(['*' => Http::response('nope', 403)]);

        $this->artisan('whatnot:probe')
            ->expectsOutputToContain('refused (403)')
            ->assertExitCode(1);
    }

    public function test_rate_limiting_is_distinguished_from_refusal(): void
    {
        Http::fake(['*' => Http::response('slow down', 429)]);

        $this->artisan('whatnot:probe')
            ->expectsOutputToContain('rate limited (429)')
            ->assertExitCode(1);
    }

    public function test_it_stops_early_when_there_is_no_session_to_probe_with(): void
    {
        @unlink($this->cookieFile);
        $bootstrap = storage_path('whatnot-cookies.json');
        $moved     = file_exists($bootstrap);

        if ($moved) {
            rename($bootstrap, $bootstrap . '.probe-test-backup');
        }

        try {
            $this->artisan('whatnot:probe')
                ->expectsOutputToContain('whatnot:login')
                ->assertExitCode(1);
        } finally {
            if ($moved) {
                rename($bootstrap . '.probe-test-backup', $bootstrap);
            }
        }
    }
}
