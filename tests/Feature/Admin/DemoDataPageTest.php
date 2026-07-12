<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\DemoData;
use App\Models\InventoryCase;
use App\Models\StreamerLogEntry;
use App\Models\User;
use App\Models\WhatnotShowOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DemoDataPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.owner_email' => 'owner@vortexbreaks.com']);
    }

    private function owner(): User
    {
        return User::factory()->create(['email' => 'owner@vortexbreaks.com']);
    }

    public function test_page_is_owner_or_super_admin_only(): void
    {
        $this->assertFalse(DemoData::canAccess()); // guest

        $this->actingAs(User::factory()->create(['email' => 'someone@else.com']));
        $this->assertFalse(DemoData::canAccess()); // ordinary user

        // A plain admin must NOT be able to seed (could hit a production DB).
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create(['email' => 'admin@else.com']);
        $admin->assignRole('admin');
        $this->actingAs($admin);
        $this->assertFalse(DemoData::canAccess());

        // Super admin (the dev/testing account) can.
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $super = User::factory()->create(['email' => 'super@else.com']);
        $super->assignRole('super_admin');
        $this->actingAs($super);
        $this->assertTrue(DemoData::canAccess());

        // The owner can.
        $this->actingAs($this->owner());
        $this->assertTrue(DemoData::canAccess());
    }

    public function test_load_action_seeds_every_flow(): void
    {
        // Fresh DB — none of the demo artifacts exist yet.
        $this->assertSame(0, WhatnotShowOrder::count());
        $this->assertSame(0, InventoryCase::whereNotNull('barcode')->count());

        Livewire::actingAs($this->owner());
        Livewire::test(DemoData::class)->callAction('load');

        // Orders (mapped and unmapped), log entries across states, and a
        // scannable barcoded pallet all landed.
        $this->assertGreaterThan(0, WhatnotShowOrder::count());
        $this->assertGreaterThan(0, WhatnotShowOrder::whereNotNull('inventory_item_id')->count());
        $this->assertGreaterThan(0, WhatnotShowOrder::whereNull('inventory_item_id')->count());
        $this->assertTrue(InventoryCase::where('barcode', 'VX-CASE-0001')->exists());

        $statuses = StreamerLogEntry::pluck('status')->unique();
        $this->assertTrue($statuses->contains('admin_approved'));
        $this->assertTrue($statuses->contains('pending'));

        // Streamer login accounts exist and are linked.
        $this->assertTrue(User::where('email', 'jordan@vortexbreaks.com')->exists());
    }

    public function test_load_action_is_idempotent(): void
    {
        Livewire::actingAs($this->owner());
        Livewire::test(DemoData::class)->callAction('load');
        $firstCount = WhatnotShowOrder::count();

        Livewire::test(DemoData::class)->callAction('load');
        $this->assertSame($firstCount, WhatnotShowOrder::count(), 'Re-running should not duplicate orders');
    }
}
