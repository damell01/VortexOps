<?php

namespace Tests\Feature\Console;

use App\Models\Show;
use App\Services\WhatnotApiShowImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The retention rules added to storage:trim: automatic and temporary data
 * goes after a while; what people entered, and what the app still compares
 * against, stays.
 */
class StorageRetentionTest extends TestCase
{
    use RefreshDatabase;

    public function test_old_automatic_data_goes_and_what_matters_stays(): void
    {
        $channel = DB::table('whatnot_channels')->insertGetId(['name' => 'VortexBreaks', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $show = Show::withoutEvents(fn () => Show::create(['title' => 'Retention show', 'show_date' => today(), 'status' => 'draft']))->id;

        // Webhook deliveries: handled + old goes; recent and still-failing-but-young stay.
        $hook = fn (string $id, int $days, bool $handled) => DB::table('whatnot_api_webhook_events')->insertGetId([
            'webhook_id' => $id, 'seller_id' => 's', 'topic' => 'order.updated', 'payload_sha256' => str_repeat('a', 64), 'encrypted_payload' => 'x',
            'status' => $handled ? 'processed' : 'failed', 'received_at' => now()->subDays($days), 'processed_at' => $handled ? now()->subDays($days) : null,
            'created_at' => now()->subDays($days), 'updated_at' => now()]);
        $oldHandled = $hook('w1', 45, true);
        $newHandled = $hook('w2', 5, true);
        $youngFailed = $hook('w3', 45, false);
        $oldFailed = $hook('w4', 120, false);

        // Show changes: automatic + old goes; a person's edit stays however old.
        $change = fn (string $by, string $source, int $days) => DB::table('show_change_logs')->insertGetId([
            'show_id' => $show, 'field_name' => 'title', 'old_value' => 'a', 'new_value' => 'b', 'changed_by' => $by, 'source' => $source, 'created_at' => now()->subDays($days)]);
        $oldApi = $change('system', WhatnotApiShowImporter::SOURCE, 200);
        $recentApi = $change('system', WhatnotApiShowImporter::SOURCE, 20);
        $oldHuman = $change('owner@example.test', 'manual', 400);

        // Value history: old goes, but the newest of each field stays even when old.
        $seen = fn (string $field, int $days) => DB::table('whatnot_api_observed_values')->insertGetId([
            'whatnot_channel_id' => $channel, 'resource' => 'show', 'source_id' => 'abc', 'field' => $field,
            'value_encrypted' => 'x', 'value_sha256' => str_repeat('b', 64), 'observed_at' => now()->subDays($days), 'created_at' => now()->subDays($days), 'updated_at' => now()]);
        $superseded = $seen('gross', 300);
        $newestGross = $seen('gross', 250);
        $onlyViews = $seen('views', 300);

        DB::table('cache')->insert([['key' => 'gone', 'value' => 'x', 'expiration' => now()->subHour()->getTimestamp()], ['key' => 'live', 'value' => 'x', 'expiration' => now()->addHour()->getTimestamp()]]);

        // A dry run removes nothing.
        $this->artisan('storage:trim')->assertSuccessful();
        $this->assertSame(4, DB::table('whatnot_api_webhook_events')->count());
        $this->assertSame(3, DB::table('show_change_logs')->count());

        $this->artisan('storage:trim --apply')->assertSuccessful();

        $hooks = DB::table('whatnot_api_webhook_events')->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$newHandled, $youngFailed], $hooks);
        $this->assertNotContains($oldHandled, $hooks);
        $this->assertNotContains($oldFailed, $hooks);

        $changes = DB::table('show_change_logs')->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$recentApi, $oldHuman], $changes, 'automatic changes go after 180 days; a person\'s edit never does');
        $this->assertNotContains($oldApi, $changes);

        $values = DB::table('whatnot_api_observed_values')->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$newestGross, $onlyViews], $values, 'the newest reading of each field is kept for the next comparison');
        $this->assertNotContains($superseded, $values);

        $this->assertSame(['live'], DB::table('cache')->pluck('key')->all());
    }
}
