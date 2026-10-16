<?php

namespace Tests\Feature\Reliability;

use App\Models\Show;
use App\Models\ShowIngestionLog;
use App\Models\User;
use App\Models\WhatnotChannel;
use App\Notifications\ScraperHealthDigestNotification;
use App\Services\ScraperHealthDigest;
use App\Support\ScraperStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ScraperHealthDigestTest extends TestCase
{
    use RefreshDatabase;

    private string $cookies;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-12 08:00:00');
        $this->cookies = tempnam(sys_get_temp_dir(), 'wn-cookies');
        config(['vortex.whatnot.cookies_file' => $this->cookies]);
    }

    protected function tearDown(): void
    {
        @unlink($this->cookies);
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function writeCookies(array $cookies, bool $storageState = false): void
    {
        file_put_contents($this->cookies, json_encode($storageState ? ['cookies' => $cookies, 'origins' => []] : $cookies));
    }

    public function test_session_expiry_comes_from_the_sign_in_cookies_only(): void
    {
        $this->writeCookies([
            ['name' => 'usid_session', 'value' => 'x', 'expires' => now()->addDays(40)->timestamp],
            ['name' => 'auth_token', 'value' => 'x', 'expires' => now()->addDays(5)->timestamp],
            ['name' => 'analytics_id', 'value' => 'x', 'expires' => now()->addDay()->timestamp],
            ['name' => 'session_tab', 'value' => 'x', 'expires' => -1],
        ], storageState: true);

        $this->assertTrue(ScraperStatus::sessionExpiresAt($this->cookies)->equalTo(now()->addDays(5)));
        $session = app(ScraperHealthDigest::class)->build()['session'];
        $this->assertSame('expiring', $session['state']);
        $this->assertSame(5, $session['days_left']);

        $this->writeCookies([['name' => 'auth_token', 'value' => 'x', 'expirationDate' => now()->subDay()->timestamp]]);
        $this->assertSame('expired', app(ScraperHealthDigest::class)->build()['session']['state']);

        $this->writeCookies([['name' => 'auth_token', 'value' => 'x', 'expires' => now()->addDays(60)->timestamp]]);
        $this->assertSame('ok', app(ScraperHealthDigest::class)->build()['session']['state']);

        $this->writeCookies([['name' => 'auth_token', 'value' => 'x', 'expires' => -1]]);
        $this->assertSame('unknown', app(ScraperHealthDigest::class)->build()['session']['state']);
    }

    public function test_digest_counts_imports_failures_and_gaps(): void
    {
        $this->writeCookies([['name' => 'auth_token', 'value' => 'x', 'expires' => now()->addDays(60)->timestamp]]);
        $live = WhatnotChannel::create(['name' => 'Vortex Breaks', 'status' => 'active', 'include_in_import' => true]);
        WhatnotChannel::create(['name' => 'Vortex Shop', 'status' => 'active', 'include_in_import' => true]);
        Show::withoutEvents(function () use ($live) {
            Show::create(['title' => 'Sold but no orders', 'show_date' => '2026-10-10', 'status' => 'reconciled', 'whatnot_channel_id' => $live->id, 'units_sold' => 12, 'gross_revenue' => 900]);
            Show::create(['title' => 'No figures yet', 'show_date' => '2026-10-09', 'status' => 'reconciled', 'whatnot_channel_id' => $live->id]);
            // Today's show is inside the grace day, so it is not a gap yet.
            Show::create(['title' => 'Tonight', 'show_date' => '2026-10-12', 'status' => 'reconciled', 'whatnot_channel_id' => $live->id, 'units_sold' => 5]);
        });
        ShowIngestionLog::create(['source' => 'whatnot_orders', 'status' => 'failed', 'raw_payload' => [], 'error_message' => "Exit 3: challenge\nstack"]);
        ShowIngestionLog::create(['source' => 'whatnot_orders', 'status' => 'success', 'raw_payload' => []]);

        $d = app(ScraperHealthDigest::class)->build();

        $this->assertSame(3, $d['imported']['new_shows']);
        $this->assertSame(1, $d['failures']['count']);
        $this->assertSame('Exit 3: challenge', $d['failures']['by_source'][0]['last_error']);
        $this->assertSame(1, $d['gaps']['shows_without_orders']);
        $this->assertSame(['Oct 10 — Sold but no orders'], $d['gaps']['shows_without_orders_examples']);
        $this->assertSame(1, $d['gaps']['shows_without_figures']);
        $this->assertSame(['Vortex Shop'], $d['gaps']['quiet_channels']);
        $this->assertTrue($d['needs_attention']);

        $html = html_entity_decode((string) (new ScraperHealthDigestNotification($d))->toMail(new User)->render());
        foreach (['needs attention', 'New shows', 'good until Dec 11, 2026', 'whatnot_orders', 'Exit 3: challenge', 'Sold but no orders', 'No shows imported for: Vortex Shop'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        $this->assertMatchesRegularExpression('/New shows<\/div>\s*<div[^>]*>3</', $html, 'three new shows, in its tile');
    }

    public function test_command_sends_to_the_owner(): void
    {
        Notification::fake();
        $owner = User::factory()->create(['email' => config('app.owner_email') ?: 'dbellcreations@gmail.com']);
        User::factory()->create();

        $this->artisan('reports:scraper-health-digest')->assertSuccessful();

        Notification::assertSentTo($owner, ScraperHealthDigestNotification::class);
        Notification::assertCount(1);
    }
}
