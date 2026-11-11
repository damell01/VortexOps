<?php

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** storage:trim removes only what the app no longer reads, and nothing on a dry run. */
class StorageTrimTest extends TestCase
{
    use RefreshDatabase;

    private int $channel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->channel = DB::table('whatnot_channels')->insertGetId(['name' => 'VortexBreaks', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
    }

    private function snapshot(string $resource, string $hash, int $daysAgo): int
    {
        return DB::table('whatnot_api_snapshots')->insertGetId(['whatnot_channel_id' => $this->channel, 'resource' => $resource, 'payload_sha256' => $hash,
            'payload' => str_repeat('x', 100), 'fetched_at' => now()->subDays($daysAgo), 'created_at' => now()->subDays($daysAgo), 'updated_at' => now()]);
    }

    public function test_it_trims_old_copies_and_keeps_what_the_app_reads(): void
    {
        $oldOrders = $this->snapshot('orders', 'a', 20);
        $newestOrders = $this->snapshot('orders', 'b', 10);   // old, but the newest of its feed
        $recent = $this->snapshot('ledger', 'c', 2);

        $old = DB::table('whatnot_ledger_entries')->insertGetId(['whatnot_channel_id' => $this->channel, 'amount' => -5, 'transaction_type' => 'Shipping charge',
            'dedup_key' => 'k1', 'created_date' => now()->subDays(40), 'created_at' => now()->subDays(40), 'updated_at' => now(),
            'raw_data' => json_encode(['ledger_transaction_id' => 'L1', 'livestream_id' => 'live-9', '_source' => 'seller_api', 'description' => str_repeat('long text ', 50), 'buyer' => ['name' => 'x']])]);
        $fresh = DB::table('whatnot_ledger_entries')->insertGetId(['whatnot_channel_id' => $this->channel, 'amount' => 10, 'transaction_type' => 'Tip',
            'dedup_key' => 'k2', 'created_date' => now(), 'created_at' => now(), 'updated_at' => now(), 'raw_data' => json_encode(['ledger_transaction_id' => 'L2', 'description' => 'keep me'])]);

        $show = DB::table('shows')->insertGetId(['title' => 'Show', 'show_date' => now()->toDateString(), 'status' => 'reconciled', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('show_change_logs')->insert([
            ['show_id' => $show, 'field_name' => 'whatnot_net', 'old_value' => '100', 'new_value' => '100', 'changed_by' => 'sync', 'source' => 'whatnot_import', 'created_at' => now()],
            ['show_id' => $show, 'field_name' => 'whatnot_net', 'old_value' => '100', 'new_value' => '90', 'changed_by' => 'sync', 'source' => 'whatnot_import', 'created_at' => now()],
        ]);

        // Dry run: nothing changes.
        $this->artisan('storage:trim')->assertSuccessful();
        $this->assertSame(3, DB::table('whatnot_api_snapshots')->count());
        $this->assertSame(2, DB::table('show_change_logs')->count());

        $this->artisan('storage:trim', ['--apply' => true])->assertSuccessful();

        $this->assertSame([$newestOrders, $recent], DB::table('whatnot_api_snapshots')->orderBy('id')->pluck('id')->all());
        $this->assertFalse(DB::table('whatnot_api_snapshots')->where('id', $oldOrders)->exists());

        $slim = json_decode(DB::table('whatnot_ledger_entries')->where('id', $old)->value('raw_data'), true);
        $this->assertSame(['ledger_transaction_id' => 'L1', 'livestream_id' => 'live-9', '_source' => 'seller_api'], $slim);
        $this->assertSame(-5.0, (float) DB::table('whatnot_ledger_entries')->where('id', $old)->value('amount')); // the entry itself stays
        $this->assertStringContainsString('keep me', DB::table('whatnot_ledger_entries')->where('id', $fresh)->value('raw_data'));

        // Only the real change is left.
        $this->assertSame(['90'], DB::table('show_change_logs')->pluck('new_value')->all());
    }
}
