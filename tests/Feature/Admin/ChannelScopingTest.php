<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\InventoryLocationResource;
use App\Filament\Resources\StreamerResource;
use App\Models\InventoryLocation;
use App\Models\Streamer;
use App\Models\User;
use App\Support\AdminModules;
use App\Support\ChannelContext;
use App\Models\WhatnotChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ChannelScopingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        AdminModules::flushMemo();
        \App\Models\Setting::set('enabled_admin_modules', json_encode(array_keys(AdminModules::definitions())));
        AdminModules::flushMemo();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin);
    }

    public function test_streamer_list_shows_everyone_when_unscoped(): void
    {
        $breaks   = WhatnotChannel::create(['name' => 'Vortex Breaks', 'status' => 'active']);
        $collects = WhatnotChannel::create(['name' => 'Vortex Collects', 'status' => 'active']);
        Streamer::create(['name' => 'A', 'status' => 'active', 'whatnot_channel_id' => $breaks->id]);
        Streamer::create(['name' => 'B', 'status' => 'active', 'whatnot_channel_id' => $collects->id]);

        $this->assertSame(2, StreamerResource::getEloquentQuery()->count());
    }

    public function test_streamer_list_scopes_to_active_channel(): void
    {
        $breaks   = WhatnotChannel::create(['name' => 'Vortex Breaks', 'status' => 'active']);
        $collects = WhatnotChannel::create(['name' => 'Vortex Collects', 'status' => 'active']);
        Streamer::create(['name' => 'A', 'status' => 'active', 'whatnot_channel_id' => $breaks->id]);
        Streamer::create(['name' => 'B', 'status' => 'active', 'whatnot_channel_id' => $collects->id]);

        ChannelContext::setActive($breaks->id);

        $names = StreamerResource::getEloquentQuery()->pluck('name')->all();

        $this->assertSame(['A'], $names);
    }

    public function test_inventory_location_list_scopes_to_active_channel(): void
    {
        $breaks   = WhatnotChannel::create(['name' => 'Vortex Breaks', 'status' => 'active']);
        $collects = WhatnotChannel::create(['name' => 'Vortex Collects', 'status' => 'active']);
        InventoryLocation::create(['name' => 'Warehouse A', 'type' => 'main_storage', 'status' => 'active', 'whatnot_channel_id' => $breaks->id]);
        InventoryLocation::create(['name' => 'Warehouse B', 'type' => 'main_storage', 'status' => 'active', 'whatnot_channel_id' => $collects->id]);

        ChannelContext::setActive($collects->id);

        $names = InventoryLocationResource::getEloquentQuery()->pluck('name')->all();

        $this->assertSame(['Warehouse B'], $names);
    }

    public function test_inventory_location_list_shows_all_when_unscoped(): void
    {
        $breaks   = WhatnotChannel::create(['name' => 'Vortex Breaks', 'status' => 'active']);
        $collects = WhatnotChannel::create(['name' => 'Vortex Collects', 'status' => 'active']);
        InventoryLocation::create(['name' => 'Warehouse A', 'type' => 'main_storage', 'status' => 'active', 'whatnot_channel_id' => $breaks->id]);
        InventoryLocation::create(['name' => 'Warehouse B', 'type' => 'main_storage', 'status' => 'active', 'whatnot_channel_id' => $collects->id]);

        $this->assertSame(2, InventoryLocationResource::getEloquentQuery()->count());
    }
}
