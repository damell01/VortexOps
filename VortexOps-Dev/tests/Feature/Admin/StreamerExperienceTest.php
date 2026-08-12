<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\ShowResource\Pages\ListShows;
use App\Filament\Resources\ShowResource\Pages\ViewShow;
use App\Filament\Resources\ShowResource\RelationManagers\OrdersRelationManager;
use App\Filament\Resources\StreamerLogResource\Pages\ListStreamerLogEntries;
use App\Filament\Widgets\StreamerShowsToReviewWidget;
use App\Models\InventoryItem;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\StreamerLogEntry;
use App\Models\User;
use App\Models\WhatnotShowOrder;
use App\Support\AdminModules;
use App\Support\NotificationLinks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StreamerExperienceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        AdminModules::flushMemo();
        config(['app.owner_email' => 'dbellcreations@gmail.com']);
        Role::firstOrCreate(['name' => 'streamer', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }

    private function streamerUser(): array
    {
        $streamer = Streamer::create(['name' => 'S1', 'status' => 'active']);
        $user = User::factory()->create(['email' => 'streamer@test.com']);
        $user->assignRole('streamer');
        $streamer->update(['user_id' => $user->id]);
        return [$user, $streamer];
    }

    // ── Shows-to-review widget ────────────────────────────────────────────────

    public function test_shows_to_review_lists_pending_entries_with_action(): void
    {
        [$user, $streamer] = $this->streamerUser();
        $show = Show::create(['title' => 'Enrich Me', 'show_date' => '2026-06-05', 'status' => 'pending_review', 'created_by' => $user->id]);
        StreamerLogEntry::create(['show_id' => $show->id, 'streamer_id' => $streamer->id, 'status' => 'pending']);

        Livewire::actingAs($user);

        Livewire::test(StreamerShowsToReviewWidget::class)
            ->loadTable()
            ->assertSee('Enrich Me')
            ->assertSee('Add items');
    }

    public function test_shows_to_review_hidden_for_admins(): void
    {
        $admin = User::factory()->create(['email' => 'admin@test.com']);
        $admin->assignRole('admin');
        $this->actingAs($admin);

        $this->assertFalse(StreamerShowsToReviewWidget::canView());
    }

    // ── Filter-preset tabs ────────────────────────────────────────────────────

    public function test_shows_list_exposes_preset_tabs(): void
    {
        $admin = User::factory()->create(['email' => 'admin@test.com']);
        $admin->assignRole('admin');
        Livewire::actingAs($admin);

        $tabs = Livewire::test(ListShows::class)->instance()->getTabs();

        $this->assertArrayHasKey('needs_review', $tabs);
        $this->assertArrayHasKey('this_week', $tabs);
        $this->assertArrayHasKey('unreconciled', $tabs);
    }

    public function test_streamer_log_list_exposes_preset_tabs(): void
    {
        [$user] = $this->streamerUser();
        Livewire::actingAs($user);

        $tabs = Livewire::test(ListStreamerLogEntries::class)->instance()->getTabs();

        $this->assertArrayHasKey('to_review', $tabs);
        $this->assertArrayHasKey('reviewed', $tabs);
    }

    // ── Actionable notification deep-link ─────────────────────────────────────

    public function test_streamer_notification_links_to_their_log_entry(): void
    {
        [$user, $streamer] = $this->streamerUser();
        $show = Show::create(['title' => 'Notif Show', 'show_date' => '2026-06-05', 'status' => 'pending_review', 'created_by' => $user->id]);
        $entry = StreamerLogEntry::create(['show_id' => $show->id, 'streamer_id' => $streamer->id, 'status' => 'pending']);

        $url = NotificationLinks::forShow($show->id, $user);

        // Streamer lands on their own log entry's edit form (the enrichment page).
        $this->assertStringContainsString("/streamer-logs/{$entry->getKey()}/edit", $url);
    }

    public function test_admin_notification_links_to_the_show(): void
    {
        $admin = User::factory()->create(['email' => 'admin@test.com']);
        $admin->assignRole('admin');
        $show = Show::create(['title' => 'Notif Show', 'show_date' => '2026-06-05', 'status' => 'pending_review', 'created_by' => $admin->id]);

        $url = NotificationLinks::forShow($show->id, $admin);

        $this->assertStringContainsString("/shows/{$show->getKey()}", $url);
    }

    // ── Fill-costs enrichment action ──────────────────────────────────────────

    public function test_fill_costs_populates_unit_cost_from_inventory(): void
    {
        $admin = User::factory()->create(['email' => 'admin@test.com']);
        $admin->assignRole('admin');

        $item = InventoryItem::create(['name' => 'Boxo', 'unit_cost' => 7.50, 'is_active' => true]);
        $show = Show::create(['title' => 'Costs', 'show_date' => '2026-06-06', 'status' => 'draft', 'created_by' => $admin->id]);

        // mapped, no cost yet
        $mapped = WhatnotShowOrder::create(['show_id' => $show->id, 'item_name' => 'A', 'buyer_username' => 'b', 'quantity' => 3, 'inventory_item_id' => $item->id, 'status' => 'completed', 'show_date' => $show->show_date]);
        // unmapped — must be left alone
        $unmapped = WhatnotShowOrder::create(['show_id' => $show->id, 'item_name' => 'B', 'buyer_username' => 'c', 'quantity' => 1, 'status' => 'completed', 'show_date' => $show->show_date]);

        Livewire::actingAs($admin);

        Livewire::test(OrdersRelationManager::class, [
            'ownerRecord' => $show,
            'pageClass'   => ViewShow::class,
        ])->callTableAction('fill_costs');

        $mapped->refresh();
        $unmapped->refresh();

        $this->assertEquals(7.50, (float) $mapped->unit_cost);
        $this->assertEquals(22.50, (float) $mapped->total_cost); // 3 × 7.50
        $this->assertNull($unmapped->unit_cost);
    }
}
