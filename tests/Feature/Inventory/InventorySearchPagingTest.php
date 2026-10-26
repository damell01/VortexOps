<?php

namespace Tests\Feature\Inventory;

use App\Filament\Pages\InventorySearch;
use App\Models\InventoryItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Find Inventory drew up to 500 rows on every keystroke, and redrawing that
 * table took the browser seconds. It now draws 50 at a time.
 */
class InventorySearchPagingTest extends TestCase
{
    use RefreshDatabase;

    public function test_results_come_fifty_at_a_time_and_a_new_search_starts_over(): void
    {
        $this->enableAdminModules();
        $this->actingAs(User::factory()->create(['email' => config('app.owner_email')]));
        foreach (range(1, 120) as $i) {
            InventoryItem::create(['name' => sprintf('Topps Box %03d', $i), 'sku' => 'T-'.$i, 'is_active' => true]);
        }
        InventoryItem::create(['name' => 'Panini Pack', 'sku' => 'P-1', 'is_active' => true]);

        $page = Livewire::test(InventorySearch::class)->set('searchQuery', 'topps');
        $this->assertCount(50, $page->instance()->results);
        $this->assertSame(120, $page->instance()->totalResults);
        $page->assertSee('Show 50 more');

        $page->call('showMore')->call('showMore');
        $this->assertCount(120, $page->instance()->results);
        $page->assertDontSee('more</button>', false);

        $page->set('searchQuery', 'panini');
        $this->assertSame(['Panini Pack'], $page->instance()->results->pluck('name')->all());
        $this->assertSame(InventorySearch::PAGE, $page->get('shown'));

        // An unknown sort column falls back to name instead of reaching SQL.
        $page->set('searchQuery', 'topps')->set('sortBy', 'name; drop table products')->assertOk();
        $this->assertSame('Topps Box 001', $page->instance()->results->first()->name);
    }
}
