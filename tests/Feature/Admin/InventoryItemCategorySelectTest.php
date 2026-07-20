<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\InventoryItemResource\Pages\CreateInventoryItem;
use App\Models\InventoryItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The category field was a free-text input, so every item was one typo away
 * from splitting a category in two ("Baseball Cards" vs "baseball cards").
 * Converted to a searchable select sourced from existing categories, with a
 * "+ create" option that lets you type a brand new one inline — no separate
 * categories table, still just a string column on inventory_items.
 */
class InventoryItemCategorySelectTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $u = User::factory()->create(['email' => 'admin@test.com']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $u->assignRole('admin');
        return $u;
    }

    public function test_category_select_offers_existing_categories(): void
    {
        $this->actingAs($this->admin());
        InventoryItem::create(['sku' => 'A1', 'name' => 'Existing Item', 'category' => 'Baseball Cards', 'unit_cost' => 1, 'is_active' => true]);

        Livewire::test(CreateInventoryItem::class)
            ->assertFormFieldExists('category')
            ->fillForm(['sku' => 'A2', 'name' => 'New Item', 'category' => 'Baseball Cards', 'unit_cost' => 1])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('products', ['sku' => 'A2', 'category' => 'Baseball Cards']);
    }

    public function test_create_option_adds_a_brand_new_category(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(CreateInventoryItem::class)
            ->fillForm(['sku' => 'B1', 'name' => 'Brand New Category Item', 'unit_cost' => 1])
            ->callFormComponentAction('category', 'createOption', data: ['category' => 'Trading Cards'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('products', ['sku' => 'B1', 'category' => 'Trading Cards']);
    }
}
