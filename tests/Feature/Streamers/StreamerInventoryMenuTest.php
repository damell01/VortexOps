<?php

namespace Tests\Feature\Streamers;

use App\Models\Streamer;
use App\Models\User;
use App\Support\NavVisibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** A streamer's Inventory menu is just the inventory list; staff keep the tools. */
class StreamerInventoryMenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_streamers_only_get_all_inventory_under_inventory(): void
    {
        $this->enableAdminModules();
        Role::findOrCreate('streamer', 'web');
        NavVisibility::setVisibleForRole('streamer', [
            \App\Filament\Resources\InventoryItemResource::class,
            \App\Filament\Pages\QuickAddStock::class,
            \App\Filament\Pages\InventoryScanner::class,
            \App\Filament\Pages\StockTransfer::class,
            \App\Filament\Pages\EndOfStreamForm::class,
        ]);
        NavVisibility::flushMemo();
        $user = User::factory()->create();
        $user->assignRole('streamer');
        Streamer::create(['name' => 'Jay', 'user_id' => $user->id, 'status' => 'active']);
        $this->actingAs($user->fresh());

        $menu = view('filament.components.desktop-mega-nav')->render();
        $this->assertStringContainsString('All Inventory', $menu);
        foreach (['Quick Add Stock', 'Scan Inventory', 'Transfer Stock'] as $tool) {
            $this->assertStringNotContainsString($tool, $menu, "{$tool} is not in a streamer's menu");
        }
    }
}
