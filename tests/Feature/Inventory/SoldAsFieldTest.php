<?php

namespace Tests\Feature\Inventory;

use App\Filament\Resources\InventoryItemResource\Pages\ListInventoryItems;
use App\Models\InventoryItem;
use App\Models\Product;
use App\Models\User;
use App\Services\ProductSheetImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Auction / BIN / Both, as a field rather than a sentence in the notes.
 *
 * It was imported into notes for months because there was nowhere else to put
 * it, which kept the information and made it useless: you cannot filter a
 * note, or report on one. These tests are about the three things that changed
 * — it is stored, it is filterable, and the old notes were moved rather than
 * left to disagree with the new column.
 */
class SoldAsFieldTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return (User::firstWhere('email', config('app.owner_email'))
            ?? User::factory()->create(['email' => config('app.owner_email')]))->fresh();
    }

    private function importer(): ProductSheetImporter
    {
        return app(ProductSheetImporter::class);
    }

    private function rows(array $rows): array
    {
        return array_map(fn (array $row, int $i) => $row + [
            'line'       => $i + 2,
            'sku'        => null,
            'type'       => null,
            'sold_as'    => null,
            'cost'       => null,
            'sale_price' => null,
            'warnings'   => [],
        ], $rows, array_keys($rows));
    }

    public function test_the_import_puts_it_in_its_own_column(): void
    {
        $this->importer()->apply($this->rows([
            ['name' => 'Gem Box', 'sku' => 'GEM-1', 'sold_as' => 'Both'],
        ]));

        $product = Product::firstWhere('sku', 'GEM-1');

        $this->assertSame('Both', $product->sold_as);
        // And not, as it used to, into a text field nothing can query.
        $this->assertStringNotContainsString('Sold as', (string) $product->notes);
    }

    public function test_a_vendor_spelling_lands_on_the_same_value_as_ours(): void
    {
        $this->importer()->apply($this->rows([
            ['name' => 'A', 'sku' => 'A-1', 'sold_as' => 'buy it now'],
            ['name' => 'B', 'sku' => 'B-1', 'sold_as' => 'AUCTION'],
            ['name' => 'C', 'sku' => 'C-1', 'sold_as' => 'either'],
        ]));

        $this->assertSame('BIN', Product::firstWhere('sku', 'A-1')->sold_as);
        $this->assertSame('Auction', Product::firstWhere('sku', 'B-1')->sold_as);
        $this->assertSame('Both', Product::firstWhere('sku', 'C-1')->sold_as);
    }

    public function test_a_value_nobody_here_has_seen_is_kept_rather_than_dropped(): void
    {
        // The sheet is somebody else's document. An unexpected word in it is
        // information, and throwing it away is the one thing that cannot be
        // undone later.
        $this->importer()->apply($this->rows([
            ['name' => 'Odd', 'sku' => 'ODD-1', 'sold_as' => 'Giveaway only'],
        ]));

        $this->assertSame('Giveaway only', Product::firstWhere('sku', 'ODD-1')->sold_as);
    }

    public function test_it_is_not_overwritten_by_a_re_import_unless_asked(): void
    {
        Product::create(['name' => 'Gem Box', 'sku' => 'GEM-1', 'sold_as' => 'Auction', 'is_active' => true]);

        $rows = $this->rows([['name' => 'Gem Box', 'sku' => 'GEM-1', 'sold_as' => 'BIN']]);

        $this->importer()->apply($rows);
        $this->assertSame('Auction', Product::firstWhere('sku', 'GEM-1')->sold_as);

        $this->importer()->apply($rows, overwrite: true);
        $this->assertSame('BIN', Product::firstWhere('sku', 'GEM-1')->sold_as);
    }

    public function test_the_preview_names_the_field_the_screen_names(): void
    {
        $plan = $this->importer()->plan($this->rows([
            ['name' => 'Gem Box', 'sku' => 'GEM-1', 'sold_as' => 'Both'],
        ]));

        $this->assertContains('Sold as', array_column($plan['rows'][0]['changes'], 'field'));
    }

    public function test_the_catalogue_can_be_filtered_by_it(): void
    {
        $auction = InventoryItem::create(['name' => 'Auction Box', 'sku' => 'AU-1', 'sold_as' => 'Auction', 'is_active' => true]);
        $bin     = InventoryItem::create(['name' => 'BIN Box', 'sku' => 'BIN-1', 'sold_as' => 'BIN', 'is_active' => true]);

        Livewire::actingAs($this->admin())
            ->test(ListInventoryItems::class)
            ->loadTable()
            ->assertCanSeeTableRecords([$auction, $bin])
            ->filterTable('sold_as', 'Auction')
            ->assertCanSeeTableRecords([$auction])
            ->assertCanNotSeeTableRecords([$bin]);
    }

    public function test_the_migration_moved_the_old_notes_into_the_column(): void
    {
        // Products imported before the column existed carry the fact in their
        // notes. Re-running that migration step has to find them.
        $product = Product::create([
            'name'      => 'Legacy Box',
            'sku'       => 'LEG-1',
            'notes'     => "Watch the corners.\nSold as: Both",
            'is_active' => true,
        ]);

        DB::table('products')->where('id', $product->id)->update(['sold_as' => null]);

        $this->artisan('migrate:refresh', ['--path' => 'database/migrations/2026_08_26_090000_add_sold_as_to_products.php'])
            ->assertSuccessful();

        $fresh = $product->fresh();

        $this->assertSame('Both', $fresh->sold_as);
        $this->assertSame('Watch the corners.', $fresh->notes);
    }
}
