<?php

namespace Tests\Feature\Console;

use App\Services\WhatnotScraper;
use Tests\TestCase;

/**
 * Covers the cookie-import half of `whatnot:login`.
 *
 * The verification half shells out to Playwright and talks to whatnot.com, so
 * every case here passes --no-verify: what is under test is whether a given
 * export shape lands on disk in the form the scraper reads, which is the part
 * that has actually gone wrong.
 */
class WhatnotLoginTest extends TestCase
{
    private string $cookiesFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cookiesFile = app(WhatnotScraper::class)->cookiesFilePath();

        if (file_exists($this->cookiesFile)) {
            rename($this->cookiesFile, $this->cookiesFile . '.test-backup');
        }
    }

    protected function tearDown(): void
    {
        @unlink($this->cookiesFile);

        if (file_exists($this->cookiesFile . '.test-backup')) {
            rename($this->cookiesFile . '.test-backup', $this->cookiesFile);
        }

        parent::tearDown();
    }

    /** @param array<int, array<string, mixed>>|array<string, mixed> $payload */
    private function importFile(array $payload): int
    {
        $path = tempnam(sys_get_temp_dir(), 'wn-cookies-');
        file_put_contents($path, json_encode($payload));

        try {
            return $this->artisan('whatnot:login', ['--cookie-file' => $path, '--no-verify' => true])->run();
        } finally {
            @unlink($path);
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function savedCookies(): array
    {
        return json_decode((string) file_get_contents($this->cookiesFile), true);
    }

    public function test_it_imports_a_bare_cookie_editor_export(): void
    {
        $exit = $this->importFile([
            ['name' => 'sessionid', 'value' => 'abc', 'domain' => '.whatnot.com', 'httpOnly' => true],
        ]);

        $this->assertSame(0, $exit);
        $this->assertSame('sessionid', $this->savedCookies()[0]['name']);
        $this->assertTrue($this->savedCookies()[0]['httpOnly']);
    }

    public function test_it_imports_a_playwright_storage_state_export(): void
    {
        // storageState nests the array under "cookies" — the shape that used to
        // be rejected as "not valid JSON" despite being perfectly good JSON.
        $exit = $this->importFile([
            'cookies' => [['name' => 'sessionid', 'value' => 'abc', 'domain' => '.whatnot.com']],
            'origins' => [],
        ]);

        $this->assertSame(0, $exit);
        $this->assertSame('sessionid', $this->savedCookies()[0]['name']);
    }

    public function test_it_drops_cloudflares_own_cookies(): void
    {
        // None of these travel between machines. cf_clearance and __cf_bm are
        // bound to the IP and User-Agent that earned them, so replaying one from
        // a laptop is exactly what a stolen token looks like; the cf_chl_* pair
        // record a challenge in progress somewhere this browser is not.
        $exit = $this->importFile([
            ['name' => 'sessionid',    'value' => 'keep', 'domain' => '.whatnot.com'],
            ['name' => 'cf_clearance', 'value' => 'drop', 'domain' => '.whatnot.com'],
            ['name' => '__cf_bm',      'value' => 'drop', 'domain' => '.whatnot.com'],
            ['name' => 'cf_chl_2',     'value' => 'drop', 'domain' => '.whatnot.com'],
            ['name' => 'cf_chl_prog',  'value' => 'drop', 'domain' => '.whatnot.com'],
        ]);

        $this->assertSame(0, $exit);
        $this->assertSame(['sessionid'], array_column($this->savedCookies(), 'name'));
    }

    public function test_it_drops_cookies_from_other_domains(): void
    {
        $exit = $this->importFile([
            ['name' => 'sessionid', 'value' => 'abc', 'domain' => '.whatnot.com'],
            ['name' => '_ga', 'value' => 'xyz', 'domain' => '.google.com'],
        ]);

        $this->assertSame(0, $exit);
        $this->assertCount(1, $this->savedCookies());
        $this->assertSame('sessionid', $this->savedCookies()[0]['name']);
    }

    public function test_it_normalises_the_chrome_samesite_and_expiry_spellings(): void
    {
        $this->importFile([[
            'name'           => 'sessionid',
            'value'          => 'abc',
            'domain'         => '.whatnot.com',
            'sameSite'       => 'no_restriction',
            'expirationDate' => 1893456000,
        ]]);

        $saved = $this->savedCookies()[0];

        $this->assertSame('None', $saved['sameSite']);
        $this->assertSame(1893456000, $saved['expires']);
    }

    public function test_it_imports_a_pretty_printed_multi_line_export(): void
    {
        // What Cookie-Editor actually puts on the clipboard is indented across
        // many lines, which is what broke the paste route when it read one line.
        $path = tempnam(sys_get_temp_dir(), 'wn-cookies-');
        file_put_contents($path, json_encode(
            [['name' => 'sessionid', 'value' => 'abc', 'domain' => '.whatnot.com']],
            JSON_PRETTY_PRINT,
        ));

        $exit = $this->artisan('whatnot:login', ['--cookie-file' => $path, '--no-verify' => true])->run();
        @unlink($path);

        $this->assertSame(0, $exit);
        $this->assertSame('sessionid', $this->savedCookies()[0]['name']);
    }

    public function test_it_rejects_an_export_with_no_whatnot_cookies(): void
    {
        $exit = $this->importFile([['name' => '_ga', 'value' => 'xyz', 'domain' => '.google.com']]);

        $this->assertSame(1, $exit);
        $this->assertFileDoesNotExist($this->cookiesFile);
    }

    public function test_it_rejects_json_that_is_not_a_cookie_export(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'wn-cookies-');
        file_put_contents($path, 'not json at all');

        $exit = $this->artisan('whatnot:login', ['--cookie-file' => $path, '--no-verify' => true])->run();
        @unlink($path);

        $this->assertSame(1, $exit);
        $this->assertFileDoesNotExist($this->cookiesFile);
    }

    public function test_it_reports_a_missing_file_rather_than_writing_an_empty_session(): void
    {
        $exit = $this->artisan('whatnot:login', [
            '--cookie-file' => '/nonexistent/whatnot-cookies.json',
            '--no-verify'   => true,
        ])->run();

        $this->assertSame(1, $exit);
        $this->assertFileDoesNotExist($this->cookiesFile);
    }
}
