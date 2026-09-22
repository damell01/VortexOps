<?php

namespace Tests\Feature\Inventory;

use App\Filament\Pages\InventoryScanner;
use App\Filament\Pages\QuickAddStock;
use App\Filament\Resources\PalletResource\Pages\ReceivePallet;
use App\Models\InventoryItem;
use App\Models\Pallet;
use App\Models\Setting;
use App\Models\User;
use App\Models\Vendor;
use App\Support\AdminModules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The keyword-search boxes on Scan Inventory, Quick Add Stock and Receive
 * Pallet all matched name/sku/barcode with no ORDER BY at all, so a short,
 * common search term (e.g. "al") came back in arbitrary database order —
 * results that merely contained the term buried in the middle, in no
 * particular sequence, rather than the obviously-intended match first.
 */
class KeywordSearchOrderingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin);

        Setting::set('enabled_admin_modules', json_encode(array_keys(AdminModules::definitions())));
        AdminModules::flushMemo();

        // "Crystal..." only contains "al"; "Alpha Booster Box" starts with it.
        InventoryItem::create(['name' => 'Stellar Crystal Coin Box', 'sku' => 'VB1', 'is_active' => true]);
        InventoryItem::create(['name' => 'Black Crystal Blazing Jumbo Box', 'sku' => 'VB2', 'is_active' => true]);
        InventoryItem::create(['name' => 'Alpha Booster Box', 'sku' => 'VB3', 'is_active' => true]);
    }

    public function test_scanner_keyword_search_ranks_a_name_starting_with_the_term_first(): void
    {
        $names = array_column(
            Livewire::test(InventoryScanner::class)->set('keywordSearch', 'alp')->get('keywordOptions'),
            'name',
        );

        $this->assertSame('Alpha Booster Box', $names[0]);
    }

    public function test_quick_add_stock_search_ranks_a_name_starting_with_the_term_first(): void
    {
        $names = array_column(
            Livewire::test(QuickAddStock::class)->set('productSearch', 'alp')->get('productOptions'),
            'name',
        );

        $this->assertSame('Alpha Booster Box', $names[0]);
    }

    public function test_receive_pallet_search_ranks_a_name_starting_with_the_term_first(): void
    {
        $pallet = Pallet::create([
            'vendor_id' => Vendor::create(['name' => 'V', 'status' => 'active'])->id,
            'reference' => 'PO-1',
            'status'    => 'receiving',
        ]);

        $names = array_column(
            Livewire::test(ReceivePallet::class, ['record' => $pallet])->set('itemSearch', 'alp')->get('itemSearchOptions'),
            'name',
        );

        $this->assertSame('Alpha Booster Box', $names[0]);
    }

    public function test_a_two_character_search_does_not_fire(): void
    {
        // Cuts down on the broadest, noisiest, most expensive searches —
        // "al" alone would match nearly anything with those two letters
        // anywhere in the name.
        $options = Livewire::test(InventoryScanner::class)->set('keywordSearch', 'al')->get('keywordOptions');

        $this->assertNull($options);
    }
}
