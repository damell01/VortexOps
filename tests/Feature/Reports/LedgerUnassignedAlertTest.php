<?php

namespace Tests\Feature\Reports;

use App\Console\Commands\LedgerUnassignedAlert;
use App\Filament\Resources\WhatnotLedgerResource\Pages\ListWhatnotLedgerEntries;
use App\Models\Streamer;
use App\Models\User;
use App\Models\WhatnotChannel;
use App\Models\WhatnotLedgerEntry;
use App\Services\LedgerAttributionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LedgerUnassignedAlertTest extends TestCase
{
    use RefreshDatabase;

    private WhatnotChannel $channel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableAdminModules();
        $this->channel = WhatnotChannel::create(['name' => 'Main', 'status' => 'active']);
    }

    private function entry(float $amount, string $type, $date): WhatnotLedgerEntry
    {
        return WhatnotLedgerEntry::create(['whatnot_channel_id' => $this->channel->id, 'created_date' => $date, 'amount' => $amount,
            'transaction_type' => $type, 'status' => 'Completed', 'dedup_key' => fake()->uuid()]);
    }

    public function test_counts_only_settled_unassigned_money_and_skips_payouts_and_approved(): void
    {
        $this->entry(10, 'Sales earnings', now()->subDays(5));      // counts
        $this->entry(-2.5, 'Seller fee', now()->subDays(4));        // counts
        $this->entry(99, 'Sales earnings', now()->subHours(6));     // still within the grace period
        $this->entry(-500, 'Payout', now()->subDays(5));            // bank transfer, needs no show
        $this->entry(50, 'Sales earnings', now()->subDays(60));     // too old
        $approved = $this->entry(7, 'Marketing', now()->subDays(5));
        DB::table('ledger_attributions')->insert(['whatnot_ledger_entry_id' => $approved->id, 'category' => 'marketing', 'target' => 'house',
            'status' => 'approved', 'reason' => 'Promo', 'created_at' => now(), 'updated_at' => now()]);

        $this->assertSame(['entries' => 2, 'net' => 7.5], LedgerUnassignedAlert::unassigned());
    }

    public function test_admins_get_an_in_app_alert_linking_to_the_review_and_nothing_when_clear(): void
    {
        foreach (['admin', 'super_admin', 'streamer', 'fulfillment'] as $r) Role::findOrCreate($r, 'web');
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->artisan('ledger:unassigned-alert')->assertSuccessful();
        $this->assertSame(0, $admin->notifications()->count());

        $this->entry(12, 'Sales earnings', now()->subDays(3));
        $this->artisan('ledger:unassigned-alert')->assertSuccessful();
        $note = $admin->notifications()->sole();
        $this->assertStringContainsString('1 ledger entry isn', json_encode($note->data));
        $this->assertStringContainsString('needs_attribution', json_encode($note->data));
    }

    public function test_a_correction_tied_to_a_streamer_shows_on_their_line(): void
    {
        $owner = User::factory()->create(['email' => 'dbellcreations@gmail.com']);
        $reviewer = User::factory()->create();
        $this->actingAs($owner);
        $streamer = Streamer::create(['name' => 'Jason', 'status' => 'active']);
        $entry = $this->entry(-15, 'Adjustment', now());
        $service = app(LedgerAttributionService::class);
        $service->save($entry, ['category' => 'correction', 'target' => 'streamer', 'streamer_id' => $streamer->id, 'reason' => 'Shorted card'], $owner->id);
        $service->review($entry, $reviewer->id, true);

        $row = collect(Livewire::test(ListWhatnotLedgerEntries::class)->instance()->streamerTotals)->firstWhere('streamer', 'Jason');
        $this->assertSame(-15.0, $row['correction']);
        $this->assertSame(0.0, $row['other']);
        $this->assertSame(-15.0, $row['net']);
    }
}
