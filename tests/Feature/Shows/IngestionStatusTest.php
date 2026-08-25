<?php

namespace Tests\Feature\Shows;

use App\Filament\Resources\ShowIngestionLogResource\Pages\ListShowIngestionLogs;
use App\Models\Setting;
use App\Models\Show;
use App\Models\ShowIngestionLog;
use App\Models\User;
use App\Models\WhatnotChannel;
use App\Support\AdminModules;
use App\Support\ScraperStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class IngestionStatusTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private WhatnotChannel $channel;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->admin = User::factory()->create(['email' => 'admin@test.com']);
        $this->admin->assignRole('admin');
        $this->actingAs($this->admin);

        Setting::set('enabled_admin_modules', json_encode(array_keys(AdminModules::definitions())));
        AdminModules::flushMemo();

        config(['vortex.whatnot.schedule_enabled' => true]);

        $this->channel = WhatnotChannel::create(['name' => 'Vortex Cards', 'status' => 'active']);
    }

    private function log(array $attributes = []): ShowIngestionLog
    {
        return ShowIngestionLog::create(array_merge([
            'whatnot_channel_id' => $this->channel->id,
            'source'             => 'whatnot_show_index',
            'status'             => 'success',
            'raw_payload'        => [],
        ], $attributes));
    }

    // ── The channel column ────────────────────────────────────────────────

    public function test_a_record_carries_its_own_channel_even_with_no_show(): void
    {
        // The rows worth finding are the failures, and those have no show to
        // join through — which is why a channel of their own was needed.
        $record = $this->log(['show_id' => null, 'status' => 'failed', 'error_message' => 'nope']);

        $this->assertSame($this->channel->id, $record->fresh()->channel->id);
    }

    public function test_the_table_can_be_narrowed_to_one_channel(): void
    {
        $other = WhatnotChannel::create(['name' => 'Second Channel', 'status' => 'active']);

        $mine   = $this->log();
        $theirs = $this->log(['whatnot_channel_id' => $other->id]);

        // The table defers loading, so nothing is fetched until asked.
        Livewire::test(ListShowIngestionLogs::class)
            ->loadTable()
            ->assertCanSeeTableRecords([$mine, $theirs])
            ->filterTable('whatnot_channel_id', [$this->channel->id])
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$theirs]);
    }

    public function test_a_channel_card_filters_the_log_and_clicking_it_again_clears_it(): void
    {
        $other = WhatnotChannel::create(['name' => 'Second Channel', 'status' => 'active']);

        $mine   = $this->log();
        $theirs = $this->log(['whatnot_channel_id' => $other->id]);

        $component = Livewire::test(ListShowIngestionLogs::class)->loadTable();

        $component->call('focusChannel', $this->channel->id)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$theirs]);

        $this->assertSame($this->channel->id, $component->instance()->focusedChannelId());

        // A card that can only ever narrow is a trap; clicking the one you are
        // already on goes back to everything.
        $component->call('focusChannel', $this->channel->id)
            ->assertCanSeeTableRecords([$mine, $theirs]);

        $this->assertNull($component->instance()->focusedChannelId());
    }

    public function test_problems_only_hides_the_successful_runs(): void
    {
        $ok     = $this->log();
        $broken = $this->log(['status' => 'failed', 'error_message' => 'Timed out']);

        Livewire::test(ListShowIngestionLogs::class)
            ->loadTable()
            ->filterTable('problems_only')
            ->assertCanSeeTableRecords([$broken])
            ->assertCanNotSeeTableRecords([$ok]);
    }

    public function test_the_date_range_actually_narrows_the_log(): void
    {
        // This filter took a from/until and applied neither. Filament injects
        // the table's builder by parameter *name*, and the closure called it
        // $q — so every constraint went onto a Builder resolved fresh from the
        // container and the visible rows never changed.
        $old    = $this->log();
        $recent = $this->log();

        $old->forceFill(['created_at' => now()->subMonth()])->saveQuietly();

        Livewire::test(ListShowIngestionLogs::class)
            ->loadTable()
            ->filterTable('created_at', ['from' => now()->subWeek()->toDateString()])
            ->assertCanSeeTableRecords([$recent])
            ->assertCanNotSeeTableRecords([$old]);
    }

    // ── Plain language ────────────────────────────────────────────────────

    public function test_every_source_the_importers_write_has_a_label(): void
    {
        // The old filter offered "Whatnot" and "Manual": three of the four
        // real values were missing and nothing has ever written "manual".
        $written = ['whatnot', 'whatnot_show_index', 'whatnot_spa_enrichment', 'whatnot_recent_refresh'];

        foreach ($written as $source) {
            $this->assertArrayHasKey($source, ShowIngestionLog::sourceLabels(), "{$source} has no label");
            $this->assertNotSame($source, $this->log(['source' => $source])->sourceLabel());
        }
    }

    public function test_an_enrichment_run_says_what_it_pulled(): void
    {
        $record = $this->log([
            'source'      => 'whatnot_spa_enrichment',
            'raw_payload' => ['analytics' => ['orders' => 12, 'units_sold' => 40], 'shipment_count' => 3],
        ]);

        $this->assertSame('Pulled 12 orders, 40 units, 3 shipments', $record->summary());
    }

    public function test_an_enrichment_run_that_found_nothing_says_so(): void
    {
        $record = $this->log([
            'source'      => 'whatnot_spa_enrichment',
            'raw_payload' => ['analytics' => [], 'shipment_count' => 0],
        ]);

        $this->assertSame('Analytics pulled, nothing new to record', $record->summary());
    }

    public function test_a_failure_distinguishes_a_known_show_from_an_unknown_one(): void
    {
        $show = Show::create([
            'whatnot_channel_id' => $this->channel->id,
            'title'              => 'Break Night',
            'show_date'          => '2026-06-15',
            'status'             => 'draft',
            'created_by'         => $this->admin->id,
        ]);

        $this->assertSame(
            'Import failed for this show',
            $this->log(['status' => 'failed', 'show_id' => $show->id])->summary(),
        );

        $this->assertSame(
            'Import failed before a show could be identified',
            $this->log(['status' => 'failed', 'show_id' => null])->summary(),
        );
    }

    // ── Scraper status ────────────────────────────────────────────────────

    public function test_a_failure_newer_than_the_last_success_reads_as_failing(): void
    {
        // The whole point: before this, a job failing every ten minutes showed
        // up only as a success timestamp that quietly stopped moving.
        Setting::set('whatnot_last_import_success_at', now()->subHours(6)->toISOString());
        Setting::set('whatnot_last_import_failure_at', now()->subMinutes(4)->toISOString());

        $job = collect(ScraperStatus::jobs())->firstWhere('key', 'whatnot_last_import');

        $this->assertSame('failing', $job['state']);
        $this->assertStringContainsString('Last attempt failed', $job['note']);
        $this->assertSame('failing', ScraperStatus::overall());
    }

    public function test_a_success_newer_than_the_last_failure_reads_as_ok(): void
    {
        Setting::set('whatnot_last_import_failure_at', now()->subHours(6)->toISOString());
        Setting::set('whatnot_last_import_success_at', now()->subMinutes(4)->toISOString());
        Setting::set('whatnot_last_recent_refresh_success_at', now()->subMinutes(20)->toISOString());

        $this->assertSame('ok', collect(ScraperStatus::jobs())->firstWhere('key', 'whatnot_last_import')['state']);
        $this->assertSame('ok', ScraperStatus::overall());
    }

    public function test_a_success_that_stopped_moving_reads_as_stale(): void
    {
        Setting::set('whatnot_last_import_success_at', now()->subHours(3)->toISOString());
        Setting::set('whatnot_last_recent_refresh_success_at', now()->subMinutes(10)->toISOString());

        $this->assertSame('stale', collect(ScraperStatus::jobs())->firstWhere('key', 'whatnot_last_import')['state']);
    }

    public function test_a_paused_schedule_is_reported_as_paused_not_broken(): void
    {
        config(['vortex.whatnot.schedule_enabled' => false]);

        $this->assertTrue(ScraperStatus::isPaused());
        $this->assertSame('paused', ScraperStatus::overall());

        foreach (ScraperStatus::jobs() as $job) {
            $this->assertSame('paused', $job['state']);
            $this->assertSame('Paused', $job['every']);
        }
    }

    public function test_nothing_recorded_reads_as_unknown_rather_than_healthy(): void
    {
        $this->assertSame('unknown', ScraperStatus::overall());
    }

    public function test_a_garbled_timestamp_does_not_take_the_page_down(): void
    {
        Setting::set('whatnot_last_import_success_at', 'not a date at all');

        $this->assertSame('unknown', collect(ScraperStatus::jobs())->firstWhere('key', 'whatnot_last_import')['state']);
    }

    public function test_a_silent_scheduler_is_detectable(): void
    {
        $this->assertFalse(ScraperStatus::schedulerIsRunning());

        Setting::set('scheduler_last_heartbeat', now()->subMinutes(2)->toISOString());
        $this->assertTrue(ScraperStatus::schedulerIsRunning());

        Setting::set('scheduler_last_heartbeat', now()->subHour()->toISOString());
        $this->assertFalse(ScraperStatus::schedulerIsRunning());
    }

    public function test_per_channel_activity_counts_the_last_day_only(): void
    {
        $this->log();
        $this->log(['status' => 'failed', 'error_message' => 'Timed out']);
        $this->log()->forceFill(['created_at' => now()->subDays(3)])->saveQuietly();

        $row = collect(ScraperStatus::byChannel())->first();

        $this->assertSame($this->channel->id, $row['channel']->id);
        $this->assertSame(2, $row['runs_24h']);
        $this->assertSame(1, $row['failures_24h']);
        $this->assertNotNull($row['last_success_at']);
    }

    // ── The page ──────────────────────────────────────────────────────────

    public function test_the_page_renders_the_status_panel(): void
    {
        Setting::set('whatnot_last_import_success_at', now()->subMinutes(3)->toISOString());
        Setting::set('scheduler_last_heartbeat', now()->subMinutes(1)->toISOString());
        $this->log();

        Livewire::test(ListShowIngestionLogs::class)
            ->assertSuccessful()
            ->assertSee('Whatnot importer')
            ->assertSee('Show index')
            ->assertSee('By channel')
            ->assertSee('Vortex Cards');
    }

    public function test_streamers_still_cannot_read_the_ingestion_log(): void
    {
        // These carry raw scraper errors for every channel and are not scoped
        // to anyone, so the admin-only check has to survive this rework.
        $streamer = User::factory()->create(['email' => 'streamer@test.com']);
        $this->actingAs($streamer);

        $this->assertFalse(\App\Filament\Resources\ShowIngestionLogResource::canAccess());
    }
}
