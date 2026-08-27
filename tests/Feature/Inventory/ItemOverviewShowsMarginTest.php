<?php

namespace Tests\Feature\Inventory;

use App\Filament\Resources\InventoryItemResource\Pages\ViewInventoryItem;
use App\Models\InventoryItem;
use App\Models\Setting;
use App\Models\User;
use App\Support\AdminModules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * What an item is worth selling, on the screen where you look it up.
 *
 * The overview showed what an item cost and nothing about what it sells for, so
 * answering "is this worth stocking" meant opening the edit form to read the
 * target and doing the subtraction by hand.
 *
 * Margin is quoted against effectiveCost() — the same cost the tables, the
 * snapshots and the package values use — because a margin measured off a cost
 * nothing else uses is a different number from the one on every other screen.
 */
class ItemOverviewShowsMarginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::set('enabled_admin_modules', json_encode(['inventory']));
        AdminModules::flushMemo();
    }

    private function page(InventoryItem $item)
    {
        $owner = User::firstWhere('email', config('app.owner_email'))
            ?? User::factory()->create(['email' => config('app.owner_email')]);

        return Livewire::actingAs($owner->fresh())
            ->test(ViewInventoryItem::class, ['record' => $item]);
    }

    private function item(array $attributes = []): InventoryItem
    {
        return InventoryItem::create(array_merge([
            'name'      => 'Topps Chrome Hobby Box',
            'sku'       => 'TC-HB',
            'unit_cost' => 89.50,
            'is_active' => true,
        ], $attributes));
    }

    public function test_the_sale_target_and_margin_are_on_the_overview(): void
    {
        $item = $this->item(['sale_price' => 140.00]);

        // 140.00 - 89.50 = 50.50, which is 36.1% of the target.
        $this->page($item)
            ->assertSee('Sale Target')
            ->assertSee('140.00')
            ->assertSee('Potential Margin')
            ->assertSee('50.50')
            ->assertSee('36.1%');
    }

    public function test_a_received_average_is_what_the_margin_is_measured_against(): void
    {
        // Once receiving has earned a weighted average, that is the cost the
        // rest of the app values this item at, so the margin follows it.
        $item = $this->item(['sale_price' => 140.00, 'average_cost' => 100.00]);

        $this->page($item)->assertSee('40.00');
    }

    public function test_an_item_with_no_target_shows_no_margin(): void
    {
        $item = $this->item(['sale_price' => null]);

        $this->page($item)
            ->assertSee('Sale Target')
            ->assertDontSee('Potential Margin');
    }

    public function test_a_target_with_no_cost_says_what_is_missing(): void
    {
        // Rather than printing the sale price as if it were all profit.
        $item = $this->item(['unit_cost' => 0, 'sale_price' => 140.00]);

        $this->page($item)
            ->assertSee('Potential Margin')
            ->assertSee('needs a cost');
    }
}
