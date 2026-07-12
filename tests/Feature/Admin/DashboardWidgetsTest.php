<?php

namespace Tests\Feature\Admin;

use App\Filament\Widgets\ActivityFeedWidget;
use App\Filament\Widgets\NeedsAttentionWidget;
use App\Filament\Widgets\SetupChecklistWidget;
use App\Filament\Widgets\ShowsCalendarWidget;
use App\Models\Setting;
use App\Models\Show;
use App\Models\User;
use App\Support\AdminModules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardWidgetsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        AdminModules::flushMemo();
        config(['app.owner_email' => 'dbellcreations@gmail.com']);
    }

    private function owner(): User
    {
        return User::factory()->create(['email' => 'dbellcreations@gmail.com']);
    }

    private function admin(): User
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $u = User::factory()->create(['email' => 'admin@test.com']);
        $u->assignRole('admin');
        return $u;
    }

    // ── Setup checklist ───────────────────────────────────────────────────────

    public function test_setup_checklist_shows_for_owner_on_a_fresh_install(): void
    {
        $this->actingAs($this->owner());
        $this->assertTrue(SetupChecklistWidget::canView());
    }

    public function test_setup_checklist_hidden_for_non_admins(): void
    {
        $this->actingAs(User::factory()->create(['email' => 'nobody@test.com']));
        $this->assertFalse(SetupChecklistWidget::canView());
    }

    public function test_setup_checklist_hidden_once_dismissed(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner);

        Livewire::actingAs($owner);
        Livewire::test(SetupChecklistWidget::class)->call('dismiss');

        $this->assertTrue((bool) Setting::get('setup_checklist_dismissed'));
        $this->assertFalse(SetupChecklistWidget::canView());
    }

    // ── Needs attention ───────────────────────────────────────────────────────

    public function test_needs_attention_is_admin_only(): void
    {
        $this->actingAs(User::factory()->create(['email' => 'plain@test.com']));
        $this->assertFalse(NeedsAttentionWidget::canView());

        $this->actingAs($this->admin());
        $this->assertTrue(NeedsAttentionWidget::canView());
    }

    public function test_needs_attention_shows_all_clear_when_nothing_pending(): void
    {
        $admin = $this->admin();
        Livewire::actingAs($admin);

        Livewire::test(NeedsAttentionWidget::class)
            ->assertSee('caught up');
    }

    public function test_needs_attention_surfaces_flagged_shows(): void
    {
        $admin = $this->admin();

        Show::create([
            'channel_attribution_suspect' => true,
            'title'         => 'Flagged Show',
            'show_date'     => '2026-06-15',
            'import_source' => 'auto_whatnot',
            'status'        => 'draft',
            'created_by'    => $admin->id,
        ]);

        Livewire::actingAs($admin);

        Livewire::test(NeedsAttentionWidget::class)
            ->assertSee('need a channel confirmed');
    }

    public function test_needs_attention_surfaces_dead_stock(): void
    {
        $admin = $this->admin();

        $item = \App\Models\InventoryItem::create(['name' => 'Stale Case', 'unit_cost' => 10, 'is_active' => true]);
        $loc  = \App\Models\InventoryLocation::create(['name' => 'Main']);
        \App\Models\InventoryStock::create([
            'inventory_item_id' => $item->id, 'inventory_location_id' => $loc->id, 'quantity' => 5,
        ]);
        // Last (and only) sale is well outside the dead-stock window.
        \App\Models\WhatnotShowOrder::create([
            'show_id' => Show::create(['title' => 'S', 'show_date' => now()->toDateString(), 'status' => 'reconciled', 'created_by' => $admin->id])->id,
            'inventory_item_id' => $item->id, 'buyer_username' => 'b', 'item_name' => 'Stale Case',
            'quantity' => 1, 'unit_cost' => 10, 'total_price' => 12, 'status' => 'completed',
            'show_date' => now()->subDays(200)->toDateString(),
        ]);

        Livewire::actingAs($admin);

        Livewire::test(NeedsAttentionWidget::class)
            ->assertSee('dead on the shelf');
    }

    // ── Blade widgets render (regression: $view must be set, not just getView) ──

    public function test_activity_feed_widget_renders(): void
    {
        Livewire::actingAs($this->admin());

        Livewire::test(ActivityFeedWidget::class)
            ->assertOk()
            ->assertSee('Recent Activity');
    }

    public function test_shows_calendar_widget_renders(): void
    {
        Livewire::actingAs($this->admin());

        Livewire::test(ShowsCalendarWidget::class)
            ->assertOk();
    }
}
