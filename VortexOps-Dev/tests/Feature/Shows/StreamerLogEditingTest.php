<?php

namespace Tests\Feature\Shows;

use App\Filament\Resources\StreamerLogResource;
use App\Filament\Resources\StreamerLogResource\Pages\EditStreamerLogEntry;
use App\Filament\Resources\StreamerLogResource\Pages\ListStreamerLogEntries;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\StreamerLogEntry;
use App\Models\User;
use App\Models\WhatnotShowOrder;
use App\Services\WhatnotScraper;
use App\Support\AdminModules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use ReflectionMethod;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StreamerLogEditingTest extends TestCase
{
    use RefreshDatabase;

    private int $creatorId;

    protected function setUp(): void
    {
        parent::setUp();
        AdminModules::flushMemo();
        config(['app.owner_email' => 'owner@test.com']);
        Role::firstOrCreate(['name' => 'streamer', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->creatorId = User::factory()->create(['email' => 'creator@test.com'])->id;
    }

    private function streamerUser(Streamer $s, string $email): User
    {
        $u = User::factory()->create(['email' => $email]);
        $u->assignRole('streamer');
        $s->update(['user_id' => $u->id]);
        return $u;
    }

    // ── One entry per show, visible to every streamer on it ───────────────────

    public function test_co_host_streamer_sees_the_shows_log_entry(): void
    {
        $primary = Streamer::create(['name' => 'Primary', 'status' => 'active']);
        $coHost  = Streamer::create(['name' => 'CoHost', 'status' => 'active']);
        $show = Show::create(['title' => 'Co-hosted', 'show_date' => now()->toDateString(), 'status' => 'draft', 'created_by' => $this->creatorId]);
        $show->streamers()->attach($primary->id, ['is_primary' => true]);
        $show->streamers()->attach($coHost->id);

        $m = new ReflectionMethod(WhatnotScraper::class, 'ensureStreamerLogEntry');
        $m->setAccessible(true);
        $m->invoke(app(WhatnotScraper::class), $show);

        // Exactly one entry for the show (schema enforces this).
        $this->assertEquals(1, StreamerLogEntry::where('show_id', $show->id)->count());
        $entryId = StreamerLogEntry::where('show_id', $show->id)->value('id');

        // The co-host — who is not the named streamer — still sees it.
        $coHostUser = $this->streamerUser($coHost, 'cohost@test.com');
        $this->actingAs($coHostUser);
        $this->assertTrue(StreamerLogResource::getEloquentQuery()->pluck('id')->contains($entryId));
    }

    // ── showOrders relation feeds the items editor ────────────────────────────

    public function test_show_orders_relation_returns_the_shows_items(): void
    {
        $s = Streamer::create(['name' => 'A', 'status' => 'active']);
        $show = Show::create(['title' => 'S', 'show_date' => now()->toDateString(), 'status' => 'draft', 'created_by' => $this->creatorId]);
        WhatnotShowOrder::create(['show_id' => $show->id, 'item_name' => 'X', 'buyer_username' => 'b', 'quantity' => 1, 'status' => 'completed', 'show_date' => $show->show_date]);
        $entry = StreamerLogEntry::create(['show_id' => $show->id, 'streamer_id' => $s->id, 'status' => 'pending']);

        $this->assertEquals(1, $entry->showOrders()->count());
    }

    // ── Edit lock ─────────────────────────────────────────────────────────────

    public function test_streamer_locked_out_of_approved_entry_but_admin_is_not(): void
    {
        $s = Streamer::create(['name' => 'A', 'status' => 'active']);
        $show = Show::create(['title' => 'S', 'show_date' => now()->toDateString(), 'status' => 'reconciled', 'created_by' => $this->creatorId]);
        $streamerUser = $this->streamerUser($s, 'streamer@test.com');
        $entry = StreamerLogEntry::create(['show_id' => $show->id, 'streamer_id' => $s->id, 'status' => 'admin_approved']);

        $this->actingAs($streamerUser);
        $this->assertTrue(StreamerLogResource::isLockedForCurrentUser($entry->fresh()));

        // Pending entry is editable for the streamer.
        $entry->update(['status' => 'pending']);
        $this->assertFalse(StreamerLogResource::isLockedForCurrentUser($entry->fresh()));

        // Admin is never locked, even on an approved entry.
        $entry->update(['status' => 'admin_approved']);
        $admin = User::factory()->create(['email' => 'admin@test.com']);
        $admin->assignRole('admin');
        $this->actingAs($admin);
        $this->assertFalse(StreamerLogResource::isLockedForCurrentUser($entry->fresh()));
    }

    // ── Send back reopens the entry ───────────────────────────────────────────

    public function test_admin_send_back_reopens_an_approved_entry(): void
    {
        $s = Streamer::create(['name' => 'A', 'status' => 'active']);
        $show = Show::create(['title' => 'S', 'show_date' => now()->toDateString(), 'status' => 'reconciled', 'created_by' => $this->creatorId]);
        $entry = StreamerLogEntry::create(['show_id' => $show->id, 'streamer_id' => $s->id, 'status' => 'admin_approved', 'reviewed_at' => now()]);

        $admin = User::factory()->create(['email' => 'admin@test.com']);
        $admin->assignRole('admin');
        Livewire::actingAs($admin);

        Livewire::test(ListStreamerLogEntries::class)
            ->callTableAction('send_back', $entry);

        $entry->refresh();
        $this->assertEquals('pending', $entry->status);
        $this->assertNull($entry->reviewed_at);
    }

    // ── Streamer can open their own entry's edit page ─────────────────────────

    public function test_streamer_can_open_their_own_log_entry_page(): void
    {
        $s = Streamer::create(['name' => 'A', 'status' => 'active']);
        $show = Show::create(['title' => 'My Stream', 'show_date' => now()->toDateString(), 'status' => 'draft', 'created_by' => $this->creatorId]);
        $show->streamers()->attach($s->id, ['is_primary' => true]);
        $streamerUser = $this->streamerUser($s, 'streamer@test.com');
        $entry = StreamerLogEntry::create(['show_id' => $show->id, 'streamer_id' => $s->id, 'status' => 'pending']);

        Livewire::actingAs($streamerUser);

        Livewire::test(EditStreamerLogEntry::class, ['record' => $entry->getRouteKey()])
            ->assertOk()
            ->assertSee('My Stream'); // the show label is shown
    }

    // ── Fulfillment review (pwe_labels streamers only) ────────────────────────

    public function test_pwe_labels_entry_needs_fulfillment_review_once_admin_approved(): void
    {
        $s = Streamer::create(['name' => 'A', 'status' => 'active', 'payout_type' => 'pwe_labels']);
        $show = Show::create(['title' => 'S', 'show_date' => now()->toDateString(), 'status' => 'reconciled', 'created_by' => $this->creatorId]);
        $entry = StreamerLogEntry::create(['show_id' => $show->id, 'streamer_id' => $s->id, 'status' => 'pending']);

        $this->assertFalse($entry->needsFulfillmentReview());

        $entry->update(['status' => 'admin_approved']);
        $this->assertTrue($entry->fresh()->needsFulfillmentReview());
    }

    public function test_non_pwe_labels_entry_never_needs_fulfillment_review(): void
    {
        $s = Streamer::create(['name' => 'A', 'status' => 'active', 'payout_type' => 'hourly', 'hourly_rate' => 15]);
        $show = Show::create(['title' => 'S', 'show_date' => now()->toDateString(), 'status' => 'reconciled', 'created_by' => $this->creatorId]);
        $entry = StreamerLogEntry::create(['show_id' => $show->id, 'streamer_id' => $s->id, 'status' => 'admin_approved']);

        $this->assertFalse($entry->needsFulfillmentReview());
    }

    public function test_admin_marks_fulfillment_reviewed(): void
    {
        $s = Streamer::create(['name' => 'A', 'status' => 'active', 'payout_type' => 'pwe_labels']);
        $show = Show::create(['title' => 'S', 'show_date' => now()->toDateString(), 'status' => 'reconciled', 'created_by' => $this->creatorId]);
        $entry = StreamerLogEntry::create(['show_id' => $show->id, 'streamer_id' => $s->id, 'status' => 'admin_approved', 'pwe_count' => 10, 'label_count' => 5]);

        $admin = User::factory()->create(['email' => 'admin@test.com']);
        $admin->assignRole('admin');
        Livewire::actingAs($admin);

        Livewire::test(ListStreamerLogEntries::class)
            ->callTableAction('fulfillment_review', $entry);

        $entry->refresh();
        $this->assertNotNull($entry->fulfillment_reviewed_at);
        $this->assertEquals($admin->id, $entry->fulfillment_reviewed_by);
        $this->assertFalse($entry->needsFulfillmentReview());
    }

    public function test_send_back_clears_fulfillment_review(): void
    {
        $s = Streamer::create(['name' => 'A', 'status' => 'active', 'payout_type' => 'pwe_labels']);
        $show = Show::create(['title' => 'S', 'show_date' => now()->toDateString(), 'status' => 'reconciled', 'created_by' => $this->creatorId]);
        $entry = StreamerLogEntry::create([
            'show_id' => $show->id, 'streamer_id' => $s->id, 'status' => 'admin_approved',
            'fulfillment_reviewed_at' => now(), 'fulfillment_reviewed_by' => $this->creatorId,
        ]);

        $admin = User::factory()->create(['email' => 'admin@test.com']);
        $admin->assignRole('admin');
        Livewire::actingAs($admin);

        Livewire::test(ListStreamerLogEntries::class)
            ->callTableAction('send_back', $entry);

        $entry->refresh();
        $this->assertNull($entry->fulfillment_reviewed_at);
        $this->assertNull($entry->fulfillment_reviewed_by);
    }
}
