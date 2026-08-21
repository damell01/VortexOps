<?php

namespace Tests\Feature\Inventory;

use App\Filament\Resources\InventoryItemResource;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Models\Vendor;
use App\Support\AdminModules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Everything the item form accepts has to still be there afterwards.
 *
 * A field with nowhere to go is the worst kind of bug in a form: Filament
 * collects it, the model quietly drops it as unfillable or the column simply
 * does not exist, and the save reports success. Nothing errors. You type a
 * value, press save, and it is gone — and because it saved, the natural
 * conclusion is that you did something wrong.
 *
 * The last test here is the one that matters. It reads the form's own field
 * list rather than a list somebody maintained by hand, so a field added later
 * with no column fails this without anyone remembering to come back.
 */
class InventoryItemFieldsSaveTest extends TestCase
{
    use RefreshDatabase;

    private InventoryLocation $location;
    private Vendor $vendor;

    protected function setUp(): void
    {
        parent::setUp();
        AdminModules::flushMemo();
        Setting::set('enabled_admin_modules', json_encode(['inventory']));
        AdminModules::flushMemo();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin);

        $this->location = InventoryLocation::create(['name' => 'Main', 'type' => 'main_storage', 'status' => 'active']);
        $this->vendor   = Vendor::create(['name' => 'Topps Direct', 'status' => 'active']);
        cache()->forget('inv_loc:active');
    }

    /**
     * Every product field the form actually offers, with a value that is its
     * own evidence.
     *
     * Derived from the form rather than from the columns. products carries
     * brand, sport, year, set_name, manufacturer, configuration and upc that no
     * field writes to — those are unused columns, not fields that fail to save,
     * and asserting on them would fail this test for a reason that is not a bug.
     */
    private function filled(): array
    {
        return [
            'name'                => 'Full Field Box',
            'sku'                 => 'FFB-1',
            'barcode'             => '9998887776665',
            'category'            => 'Baseball',
            'description'         => 'A description that must survive.',
            'notes'               => 'A note that must survive.',
            'unit_cost'           => 123.45,
            'average_cost'        => 111.11,
            'reorder_level'       => 7,
            'is_active'           => true,
            'is_container'        => true,
            'preferred_vendor_id' => $this->vendor->id,
        ];
    }

    public function test_every_field_on_the_create_form_is_still_there_afterwards(): void
    {
        Livewire::test(InventoryItemResource\Pages\CreateInventoryItem::class)
            ->fillForm($this->filled())
            ->call('create')
            ->assertHasNoFormErrors();

        $item = Product::firstWhere('sku', 'FFB-1');

        $this->assertNotNull($item, 'The item should have been created.');

        foreach ($this->filled() as $field => $expected) {
            $actual = $item->{$field};

            if (is_bool($expected)) {
                $this->assertSame($expected, (bool) $actual, "{$field} did not save.");

                continue;
            }

            if (is_float($expected)) {
                $this->assertEqualsWithDelta($expected, (float) $actual, 0.001, "{$field} did not save.");

                continue;
            }

            $this->assertEquals($expected, $actual, "{$field} did not save.");
        }
    }

    public function test_the_costs_save_as_typed(): void
    {
        // Money is the field people notice going missing, and the one where a
        // silently dropped value is expensive rather than annoying.
        Livewire::test(InventoryItemResource\Pages\CreateInventoryItem::class)
            ->fillForm(['name' => 'Costed', 'unit_cost' => 249.99, 'average_cost' => 200.5])
            ->call('create')
            ->assertHasNoFormErrors();

        $item = Product::firstWhere('name', 'Costed');

        $this->assertEqualsWithDelta(249.99, (float) $item->unit_cost, 0.001);
        $this->assertEqualsWithDelta(200.5, (float) $item->average_cost, 0.001);
    }

    public function test_editing_keeps_the_changes(): void
    {
        // A create test alone would pass on a field that saves once and then
        // refuses to change, which is its own kind of broken.
        $item = Product::create(['name' => 'Before', 'unit_cost' => 1, 'is_active' => true]);

        Livewire::test(InventoryItemResource\Pages\EditInventoryItem::class, ['record' => $item->id])
            ->fillForm(['name' => 'After', 'unit_cost' => 88.25, 'notes' => 'Edited'])
            ->call('save')
            ->assertHasNoFormErrors();

        $item->refresh();

        $this->assertSame('After', $item->name);
        $this->assertEqualsWithDelta(88.25, (float) $item->unit_cost, 0.001);
        $this->assertSame('Edited', $item->notes);
    }

    public function test_initial_stock_lands_where_it_was_asked_to(): void
    {
        // These three are deliberately not columns on the product — they create
        // a stock row instead. Worth pinning precisely because they look like
        // the bug this file is about.
        Livewire::test(InventoryItemResource\Pages\CreateInventoryItem::class)
            ->fillForm([
                'name'                      => 'Stocked On Create',
                'unit_cost'                 => 10,
                'initial_stock_location_id' => $this->location->id,
                'initial_stock_quantity'    => 12,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $item = Product::firstWhere('name', 'Stocked On Create');

        $this->assertEqualsWithDelta(
            12.0,
            (float) \App\Models\InventoryStock::where('inventory_item_id', $item->id)
                ->where('inventory_location_id', $this->location->id)
                ->value('quantity'),
            0.001,
        );
    }

    /**
     * The guard for fields added later.
     *
     * Reads the resource's own form rather than a list kept by hand, so a field
     * introduced next month with no column behind it fails here rather than
     * silently eating what people type into it.
     */
    public function test_no_form_field_has_nowhere_to_save(): void
    {
        $source = file_get_contents(app_path('Filament/Resources/InventoryItemResource.php'));

        preg_match_all(
            '/(?:TextInput|Select|Toggle|Textarea|DatePicker|Radio|Checkbox|ColorPicker|TagsInput|RichEditor)::make\(\'([a-z0-9_]+)\'\)/i',
            $source,
            $matches,
        );

        $columns  = Schema::getColumnListing('products');
        $fillable = (new Product)->getFillable();

        // Fields that deliberately are not product columns: they drive an
        // action, create a stock row, or describe a child relationship. Each is
        // named rather than pattern-matched, so adding one is a decision.
        $notProductFields = [
            // The childContents repeater is bound to InventoryItemContent, so
            // its fields save to that table. Naming them here rather than
            // pattern-matching on the repeater is deliberate: an earlier run of
            // this check read them as product fields and had me add a column to
            // products that already existed on the contents table.
            'childContents', 'child_inventory_item_id', 'quantity_per_parent', 'unit_type',
            // Create a stock row rather than a product column.
            'initial_stock_location_id', 'initial_stock_quantity', 'initial_stock_cost',
            // Belong to actions on this resource, not to the item itself.
            'stock', 'inventory_location_id', 'quantity', 'location_id', 'count',
            'vendor_id', 'reason', 'from_location_id', 'damaged_location_id',
            'returns_location_id', 'new_quantity', 'current_quantity', 'available',
        ];

        $orphans = [];

        foreach (array_unique($matches[1]) as $field) {
            if (in_array($field, $notProductFields, true)) {
                continue;
            }

            if (! in_array($field, $columns, true)) {
                $orphans[] = "{$field} (no column)";
            } elseif (! in_array($field, $fillable, true)) {
                $orphans[] = "{$field} (not fillable)";
            }
        }

        $this->assertSame(
            [],
            $orphans,
            "These fields are on the form but cannot be saved, so what people type is discarded silently:\n  "
                . implode("\n  ", $orphans),
        );
    }
}
